import { describe, expect, it } from "vitest";
import {
  DEFAULT_WEIGHTS,
  findCandidates,
  type CandidateProvider,
} from "./find-candidates";

function provider(opts: Partial<CandidateProvider> & { id: string; family: string }): CandidateProvider {
  return {
    id: opts.id,
    given_name: opts.given_name ?? "Test",
    family_name: opts.family,
    specialty: opts.specialty ?? "PT",
    provider_type: opts.provider_type ?? opts.specialty ?? "PT",
    active: opts.active ?? true,
    availabilityByDay:
      opts.availabilityByDay ??
      new Map([
        [1, [[540, 1020]]],
        [3, [[540, 1020]]],
        [5, [[540, 1020]]],
      ]), // 9-5 M/W/F = 1440 min/wk
    zip_codes: opts.zip_codes ?? ["33401"],
  };
}

describe("findCandidates — filtering", () => {
  it("filters out providers with wrong specialty", () => {
    const out = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: "33401" },
      [
        provider({ id: "pt1", family: "PT-One", specialty: "PT" }),
        provider({ id: "ot1", family: "OT-One", specialty: "OT" }),
      ],
    );
    expect(out.map((c) => c.provider.id)).toEqual(["pt1"]);
  });

  it("handles combined requests like PT+OT", () => {
    const out = findCandidates(
      { requested_service: "PT+OT", schedule_preference: null, subject_zip: "33401" },
      [
        provider({ id: "pt1", family: "PT-One", specialty: "PT" }),
        provider({ id: "ot1", family: "OT-One", specialty: "OT" }),
        provider({ id: "slp1", family: "SLP-One", specialty: "SLP" }),
      ],
    );
    expect(out.map((c) => c.provider.id).sort()).toEqual(["ot1", "pt1"]);
  });

  it("returns empty when no providers match the specialty", () => {
    const out = findCandidates(
      { requested_service: "SLP", schedule_preference: null, subject_zip: "33401" },
      [provider({ id: "pt1", family: "PT-One", specialty: "PT" })],
    );
    expect(out).toEqual([]);
  });

  it("excludes inactive providers", () => {
    const out = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: "33401" },
      [
        provider({ id: "pt1", family: "Active", specialty: "PT", active: true }),
        provider({ id: "pt2", family: "Inactive", specialty: "PT", active: false }),
      ],
    );
    expect(out.map((c) => c.provider.id)).toEqual(["pt1"]);
  });
});

describe("findCandidates — scoring", () => {
  it("ranks more-available providers higher when zip is the same", () => {
    const lots = provider({
      id: "lots",
      family: "Lots",
      specialty: "PT",
      availabilityByDay: new Map([
        [1, [[480, 1080]]],
        [2, [[480, 1080]]],
        [3, [[480, 1080]]],
        [4, [[480, 1080]]],
        [5, [[480, 1080]]],
      ]), // 5 × 10 hours = 3000 min (capped to fit=1.0)
    });
    const few = provider({
      id: "few",
      family: "Few",
      specialty: "PT",
      availabilityByDay: new Map([[1, [[540, 660]]]]), // 2 hours/wk
    });
    const out = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: "33401" },
      [few, lots],
    );
    expect(out[0].provider.id).toBe("lots");
  });

  it("ranks closer-zip providers higher when availability ties", () => {
    const close = provider({ id: "close", family: "Close", zip_codes: ["33401"] });
    const far = provider({ id: "far", family: "Far", zip_codes: ["10001"] });
    const out = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: "33401" },
      [far, close],
    );
    expect(out[0].provider.id).toBe("close");
    expect(out[0].proximity_score).toBeGreaterThan(out[1].proximity_score);
  });

  it("honors custom weights from tenant settings", () => {
    const closeButLow = provider({
      id: "closeLow",
      family: "CloseLow",
      zip_codes: ["33401"],
      availabilityByDay: new Map([[1, [[540, 600]]]]), // 1 hour
    });
    const farButHigh = provider({
      id: "farHigh",
      family: "FarHigh",
      zip_codes: ["10001"],
      availabilityByDay: new Map([
        [1, [[480, 1080]]],
        [2, [[480, 1080]]],
        [3, [[480, 1080]]],
        [4, [[480, 1080]]],
        [5, [[480, 1080]]],
      ]),
    });
    // Default weights (0.6 avail, 0.4 prox) — farHigh should win
    const def = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: "33401" },
      [closeButLow, farButHigh],
    );
    expect(def[0].provider.id).toBe("farHigh");

    // Heavy proximity weighting — closeLow should win
    const proxHeavy = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: "33401" },
      [closeButLow, farButHigh],
      { availability: 0.1, proximity: 0.9 },
    );
    expect(proxHeavy[0].provider.id).toBe("closeLow");
  });

  it("falls back to no proximity score when subject_zip is null", () => {
    const out = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: null },
      [provider({ id: "p1", family: "P1" })],
    );
    expect(out[0].proximity_score).toBe(0);
  });
});

describe("findCandidates — tie-breaking", () => {
  it("breaks score ties by family_name when other factors equal", () => {
    const a = provider({ id: "a", family: "Alvarez", zip_codes: ["33401"] });
    const b = provider({ id: "b", family: "Patel", zip_codes: ["33401"] });
    const out = findCandidates(
      { requested_service: "PT", schedule_preference: null, subject_zip: "33401" },
      [b, a],
    );
    expect(out.map((c) => c.provider.family_name)).toEqual(["Alvarez", "Patel"]);
  });
});

describe("findCandidates — weight defaults", () => {
  it("matches the documented 0.6 / 0.4 split", () => {
    expect(DEFAULT_WEIGHTS).toEqual({ availability: 0.6, proximity: 0.4 });
  });
});
