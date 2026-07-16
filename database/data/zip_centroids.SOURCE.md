# zip_centroids.csv — provenance & license

Reference data for the `centroid` geocoding driver (`sp_zip_centroids`). US ZIP → centroid
lat/lng. Public reference data, NOT PHI, NOT tenant-scoped. Resolved entirely in-DB, so
geocoding via this table makes zero network calls and zero egress.

Built: 2026-07-16. Rows: 41,637 (unique 5-digit ZIP keys).

## Sources (layered: Census base, GeoNames gap-fill)

1. **US Census Bureau — 2023 ZCTA Gazetteer** (base layer, 33,791 rows, `source=census`)
   - File: `2023_Gaz_zcta_national.zip`
   - URL: https://www2.census.gov/geo/docs/maps-data/data/gazetteer/2023_Gazetteer/
   - Fields used: `GEOID` → zip, `INTPTLAT`/`INTPTLONG` → centroid (the ZCTA's internal
     point, guaranteed to fall inside the polygon).
   - License: **Public domain** (US Government work, 17 U.S.C. § 105). No attribution required.

2. **GeoNames — US postal codes** (gap-fill only, 7,846 rows, `source=geonames`)
   - File: `US.zip` (`US.txt`, tab-separated)
   - URL: https://download.geonames.org/export/zip/
   - Fields used: col 2 → zip, col 10/11 → lat/lng. First occurrence per ZIP.
   - License: **CC-BY 4.0** — https://creativecommons.org/licenses/by/4.0/
   - Used ONLY for ZIPs the Census ZCTA set omits (PO-box and point ZIPs, e.g. 00501).

## Precedence
Census wins for any ZIP present in both sets (public domain + consistent internal-point
methodology). GeoNames supplies only the ZIPs Census does not cover. The `source` column
records which dataset each row came from, so the CC-BY-obligated subset is auditable and
removable.

## Attribution (required by CC-BY 4.0 for the GeoNames-derived rows)
> Contains postal-code centroid data from GeoNames (https://www.geonames.org/),
> licensed under CC-BY 4.0. Modified: filtered to US 5-digit ZIPs, deduplicated to one
> point per ZIP, used only to fill ZIPs absent from the US Census ZCTA Gazetteer.

Dropping GeoNames later: delete rows `WHERE source = 'geonames'` (~7.8k PO-box/point ZIPs);
those addresses then fail-closed to `needs_coordinates`. No Census row depends on GeoNames.

## Regenerate
Re-download both files from the URLs above and rebuild: Census `GEOID/INTPTLAT/INTPTLONG`
as the base, GeoNames (first-per-ZIP) filling only missing ZIPs, coordinates rounded to 7
decimals, output header `zip,latitude,longitude,source`, sorted by zip.
