{{--
    Leaflet + Leaflet.draw, loaded from CDN into the dashboard panel head.
    No API key required; map tiles are served from OpenStreetMap. The
    spLeafletMap() factory below is registered once globally and consumed by
    resources/views/filament/forms/leaflet-map.blade.php via Alpine x-data.
--}}
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    crossorigin=""
/>
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"
    crossorigin=""
/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js" crossorigin=""></script>

<script>
    // Reusable Alpine factory for both wizard maps. `config.mode` is either
    // 'marker' (pin-drop geocoding correction) or 'polygon' (service-zone draw).
    // It entangles directly to the Filament form state via the model paths in
    // `config`, so dropping a pin / drawing a zone writes straight back to the
    // wizard's $data without changing the backend contract.
    window.spLeafletMap = function (config) {
        return {
            mode: config.mode,
            map: null,
            marker: null,
            drawnItems: null,
            suppressWatch: false,
            // Entangled form state, passed in from the x-data expression where
            // $wire is in scope so Alpine unwraps the interceptors into live
            // values. Calling $wire.$entangle() inside init() would store a raw
            // interceptor object ({_x_interceptor:true}) instead — the value
            // would never resolve and Leaflet would project a null center.
            lat: config.lat ?? null,
            lng: config.lng ?? null,
            failed: config.failed ?? null,
            points: config.points ?? null,
            // Polygon mode only: pre-center on the applicant and draw their radius as a
            // coverage circle so they see their default service area before drawing.
            centerLat: config.centerLat ?? null,
            centerLng: config.centerLng ?? null,
            radiusMiles: config.radiusMiles ?? null,
            radiusCircle: null,
            centerMarker: null,

            init() {
                // Defer until the container is in the DOM with layout.
                this.$nextTick(() => this.buildMap());
            },

            defaultCenter() {
                return [39.5, -98.35]; // continental US
            },

            buildMap() {
                if (typeof L === 'undefined') {
                    return;
                }

                const el = this.$refs.map;
                // Polygon mode has no lat/lng of its own — fall back to the applicant's
                // center coords so the map opens on them rather than the US default.
                const pinLat = this.lat ?? this.centerLat;
                const pinLng = this.lng ?? this.centerLng;
                const hasPin = pinLat && pinLng;
                const center = hasPin ? [pinLat, pinLng] : this.defaultCenter();
                const zoom = hasPin ? 13 : 4;

                this.map = L.map(el).setView(center, zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(this.map);

                // Wizard steps are hidden until active; a map built in a hidden
                // container renders with 0 size. Recompute tiles once it shows.
                new ResizeObserver(() => this.map.invalidateSize()).observe(el);

                if (config.mode === 'marker') {
                    this.initMarker();
                } else {
                    this.initPolygon();
                }
            },

            /* ---- marker (pin-drop) mode ---- */

            initMarker() {
                if (this.lat && this.lng) {
                    this.placeMarker(this.lat, this.lng);
                }

                this.map.on('click', (e) => this.setLatLng(e.latlng.lat, e.latlng.lng));

                // React when address-blur geocoding pushes new coordinates in.
                this.$watch('lat', () => this.syncMarkerFromState());
                this.$watch('lng', () => this.syncMarkerFromState());
            },

            syncMarkerFromState() {
                if (this.suppressWatch || !this.lat || !this.lng) {
                    return;
                }

                this.placeMarker(this.lat, this.lng);
                this.map.setView([this.lat, this.lng], Math.max(this.map.getZoom(), 13));
            },

            placeMarker(lat, lng) {
                if (this.marker) {
                    this.marker.setLatLng([lat, lng]);

                    return;
                }

                this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    this.setLatLng(p.lat, p.lng);
                });
            },

            setLatLng(lat, lng) {
                this.suppressWatch = true;
                this.lat = this.round(lat);
                this.lng = this.round(lng);
                this.failed = false; // a manually placed pin counts as resolved
                this.placeMarker(this.lat, this.lng);
                this.$nextTick(() => { this.suppressWatch = false; });
            },

            /* ---- polygon (service-zone) mode ---- */

            initPolygon() {
                this.drawnItems = new L.FeatureGroup();
                this.map.addLayer(this.drawnItems);

                const existing = this.toLatLngs(this.points);
                if (existing.length >= 3) {
                    const poly = L.polygon(existing).addTo(this.drawnItems);
                    this.map.fitBounds(poly.getBounds(), { maxZoom: 13 });
                }

                // Pre-draw the applicant's coverage circle from their address + radius.
                if (this.centerLat && this.centerLng) {
                    this.drawCoverage();
                    this.$watch('radiusMiles', () => this.updateCircle());
                }

                this.map.addControl(new L.Control.Draw({
                    draw: {
                        polygon: { allowIntersection: false, showArea: true },
                        marker: false,
                        circle: false,
                        circlemarker: false,
                        polyline: false,
                        rectangle: false,
                    },
                    edit: { featureGroup: this.drawnItems },
                }));

                this.map.on(L.Draw.Event.CREATED, (e) => {
                    this.drawnItems.clearLayers(); // a provider has one service zone
                    this.drawnItems.addLayer(e.layer);
                    this.writePoints();
                });
                this.map.on(L.Draw.Event.EDITED, () => this.writePoints());
                this.map.on(L.Draw.Event.DELETED, () => this.writePoints());
            },

            /* ---- coverage circle (applicant's address + radius) ---- */

            drawCoverage() {
                const center = [parseFloat(this.centerLat), parseFloat(this.centerLng)];

                if (this.centerMarker) {
                    this.centerMarker.setLatLng(center);
                } else {
                    this.centerMarker = L.circleMarker(center, {
                        radius: 5, color: '#4f46e5', fillColor: '#4f46e5', fillOpacity: 1,
                    }).addTo(this.map);
                }

                this.updateCircle();
            },

            updateCircle() {
                if (!this.centerLat || !this.centerLng) {
                    return;
                }

                const center = [parseFloat(this.centerLat), parseFloat(this.centerLng)];
                const meters = (parseFloat(this.radiusMiles) || 0) * 1609.34;

                if (meters <= 0) {
                    return;
                }

                if (this.radiusCircle) {
                    this.radiusCircle.setLatLng(center).setRadius(meters);
                } else {
                    this.radiusCircle = L.circle(center, {
                        radius: meters, color: '#4f46e5', weight: 1, fillColor: '#4f46e5', fillOpacity: 0.08,
                    }).addTo(this.map);
                }

                // Frame the circle only while no polygon has been drawn yet.
                if (this.drawnItems && this.drawnItems.getLayers().length === 0) {
                    this.map.fitBounds(this.radiusCircle.getBounds(), { maxZoom: 13 });
                }
            },

            toLatLngs(points) {
                if (!Array.isArray(points)) {
                    return [];
                }

                return points
                    .filter((p) => p && p.latitude != null && p.longitude != null)
                    .map((p) => [parseFloat(p.latitude), parseFloat(p.longitude)]);
            },

            writePoints() {
                let coords = [];

                this.drawnItems.eachLayer((layer) => {
                    if (typeof layer.getLatLngs === 'function') {
                        const ring = layer.getLatLngs()[0] || [];
                        coords = ring.map((ll) => ({
                            latitude: this.round(ll.lat),
                            longitude: this.round(ll.lng),
                        }));
                    }
                });

                this.points = coords;
            },

            round(value) {
                return Math.round(value * 1e7) / 1e7;
            },
        };
    };
</script>
