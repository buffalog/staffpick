"use client";

import { useId, useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Turnstile } from "@marsidev/react-turnstile";

type Icd10Option = { value: string; label: string };

type Props = {
  icd10Options: Icd10Option[];
  subjectLabel: string;
  providerLabel: string;
  turnstileSiteKey: string;
};

export function IntakeForm({
  icd10Options,
  subjectLabel,
  providerLabel,
  turnstileSiteKey,
}: Props) {
  const router = useRouter();
  const [submitting, startTransition] = useTransition();
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);
  const [diagnosisFilter, setDiagnosisFilter] = useState("");
  const [selectedDiagnoses, setSelectedDiagnoses] = useState<Icd10Option[]>([]);
  const [primaryDiagnosis, setPrimaryDiagnosis] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const diagId = useId();

  const filteredDx = useMemo(() => {
    const q = diagnosisFilter.trim().toLowerCase();
    if (!q) return icd10Options.slice(0, 12);
    return icd10Options
      .filter(
        (o) =>
          o.value.toLowerCase().includes(q) || o.label.toLowerCase().includes(q),
      )
      .slice(0, 12);
  }, [icd10Options, diagnosisFilter]);

  function addDiagnosis(opt: Icd10Option) {
    if (selectedDiagnoses.some((d) => d.value === opt.value)) return;
    const next = [...selectedDiagnoses, opt];
    setSelectedDiagnoses(next);
    if (!primaryDiagnosis) setPrimaryDiagnosis(opt.value);
    setDiagnosisFilter("");
  }
  function removeDiagnosis(value: string) {
    setSelectedDiagnoses((s) => s.filter((d) => d.value !== value));
    if (primaryDiagnosis === value) setPrimaryDiagnosis(null);
  }

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    if (!turnstileToken) {
      setError("Please complete the CAPTCHA before submitting.");
      return;
    }
    if (selectedDiagnoses.length === 0) {
      setError("Select at least one ICD-10 diagnosis.");
      return;
    }

    const fd = new FormData(e.currentTarget);
    const payload = {
      turnstileToken,
      source: {
        name: String(fd.get("source_name") ?? ""),
        contact_name: String(fd.get("source_contact_name") ?? ""),
        email: String(fd.get("source_email") ?? ""),
        phone: String(fd.get("source_phone") ?? ""),
      },
      subject: {
        given_name: String(fd.get("subject_given") ?? ""),
        family_name: String(fd.get("subject_family") ?? ""),
        date_of_birth: String(fd.get("subject_dob") ?? ""),
        preferred_language: String(fd.get("subject_lang") ?? ""),
        email: String(fd.get("subject_email") ?? ""),
        phone: String(fd.get("subject_phone") ?? ""),
        address_line1: String(fd.get("subject_addr1") ?? ""),
        city: String(fd.get("subject_city") ?? ""),
        state: String(fd.get("subject_state") ?? ""),
        postal_code: String(fd.get("subject_zip") ?? ""),
      },
      caregiver: fd.get("caregiver_given")
        ? {
            given_name: String(fd.get("caregiver_given") ?? ""),
            family_name: String(fd.get("caregiver_family") ?? ""),
            phone: String(fd.get("caregiver_phone") ?? ""),
            relation: String(fd.get("caregiver_relation") ?? ""),
          }
        : null,
      diagnoses: selectedDiagnoses.map((d) => ({
        code: d.value,
        description: d.label,
        is_primary: primaryDiagnosis === d.value,
      })),
      requested_service: String(fd.get("requested_service") ?? ""),
      schedule_preference: String(fd.get("schedule_preference") ?? ""),
      notes: String(fd.get("notes") ?? ""),
    };

    startTransition(async () => {
      const res = await fetch("/api/intake", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setError(body.error ?? "Submission failed. Please try again.");
        return;
      }
      router.push("/intake/thanks");
    });
  }

  return (
    <form onSubmit={onSubmit} className="space-y-8">
      {error && (
        <div
          role="alert"
          className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      )}

      <Section title="Referring Source">
        <Field label="Organization name *" name="source_name" required />
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="Your name *" name="source_contact_name" required />
          <Field label="Your email *" name="source_email" type="email" required />
        </div>
        <Field label="Your phone *" name="source_phone" type="tel" required />
      </Section>

      <Section title={`${subjectLabel} information`}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="First name *" name="subject_given" required />
          <Field label="Last name *" name="subject_family" required />
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="Date of birth *" name="subject_dob" type="date" required />
          <Field label="Preferred language" name="subject_lang" placeholder="English" />
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label={`${subjectLabel} email`} name="subject_email" type="email" />
          <Field label={`${subjectLabel} phone`} name="subject_phone" type="tel" />
        </div>
        <Field label="Address line 1" name="subject_addr1" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Field label="City" name="subject_city" />
          <Field label="State" name="subject_state" />
          <Field label="ZIP" name="subject_zip" />
        </div>
      </Section>

      <Section title="CareGiver (optional)">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="First name" name="caregiver_given" />
          <Field label="Last name" name="caregiver_family" />
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="Phone" name="caregiver_phone" type="tel" />
          <Field label="Relation" name="caregiver_relation" placeholder="Daughter, son, …" />
        </div>
      </Section>

      <Section title="Diagnosis (ICD-10) *">
        {selectedDiagnoses.length > 0 && (
          <ul className="space-y-1">
            {selectedDiagnoses.map((d) => (
              <li
                key={d.value}
                className="flex items-center justify-between rounded-md border bg-card px-3 py-2 text-sm"
              >
                <span>
                  <span className="font-mono font-semibold mr-2">{d.value}</span>
                  {d.label}
                  {primaryDiagnosis === d.value && (
                    <span className="ml-2 text-xs uppercase tracking-wide text-muted-foreground">
                      primary
                    </span>
                  )}
                </span>
                <span className="flex items-center gap-2">
                  {primaryDiagnosis !== d.value && (
                    <button
                      type="button"
                      onClick={() => setPrimaryDiagnosis(d.value)}
                      className="text-xs underline"
                    >
                      mark primary
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => removeDiagnosis(d.value)}
                    className="text-xs text-destructive underline"
                  >
                    remove
                  </button>
                </span>
              </li>
            ))}
          </ul>
        )}
        <div>
          <label htmlFor={diagId} className="text-sm font-medium">
            Search ICD-10 code or description
          </label>
          <input
            id={diagId}
            value={diagnosisFilter}
            onChange={(e) => setDiagnosisFilter(e.target.value)}
            placeholder="e.g. dysphagia, Z47.1, stroke…"
            className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
          />
          {(diagnosisFilter || selectedDiagnoses.length === 0) && filteredDx.length > 0 && (
            <ul className="mt-2 max-h-56 overflow-auto rounded-md border bg-card divide-y">
              {filteredDx.map((opt) => (
                <li key={opt.value}>
                  <button
                    type="button"
                    onClick={() => addDiagnosis(opt)}
                    className="w-full text-left px-3 py-2 text-sm hover:bg-accent"
                  >
                    <span className="font-mono font-semibold mr-2">{opt.value}</span>
                    {opt.label}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Section>

      <Section title="Service request">
        <div className="space-y-1">
          <label className="text-sm font-medium">Requested service *</label>
          <select
            name="requested_service"
            required
            defaultValue=""
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          >
            <option value="" disabled>
              Select…
            </option>
            <option value="PT">Physical Therapy (PT)</option>
            <option value="OT">Occupational Therapy (OT)</option>
            <option value="SLP">Speech-Language Pathology (SLP)</option>
            <option value="PT+OT">PT + OT</option>
            <option value="PT+OT+SLP">PT + OT + SLP</option>
            <option value="other">Other (specify in notes)</option>
          </select>
        </div>
        <Field
          label="Schedule preference"
          name="schedule_preference"
          placeholder="e.g. weekday afternoons, 3x/week"
        />
        <div className="space-y-1">
          <label htmlFor="notes" className="text-sm font-medium">
            Notes for the {providerLabel.toLowerCase()} care team
          </label>
          <textarea
            id="notes"
            name="notes"
            rows={4}
            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
          />
        </div>
      </Section>

      <div>
        <Turnstile
          siteKey={turnstileSiteKey}
          onSuccess={setTurnstileToken}
          onError={() => setTurnstileToken(null)}
          onExpire={() => setTurnstileToken(null)}
        />
      </div>

      <button
        type="submit"
        disabled={submitting}
        className="w-full md:w-auto rounded-md bg-primary text-primary-foreground px-6 py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
      >
        {submitting ? "Submitting…" : "Submit referral"}
      </button>
    </form>
  );
}

function Section({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section className="space-y-3">
      <h2 className="text-base font-semibold">{title}</h2>
      <div className="space-y-3">{children}</div>
    </section>
  );
}

function Field({
  label,
  name,
  type = "text",
  required,
  placeholder,
}: {
  label: string;
  name: string;
  type?: string;
  required?: boolean;
  placeholder?: string;
}) {
  const id = useId();
  return (
    <div className="space-y-1">
      <label htmlFor={id} className="text-sm font-medium">
        {label}
      </label>
      <input
        id={id}
        name={name}
        type={type}
        required={required}
        placeholder={placeholder}
        className="w-full rounded-md border bg-background px-3 py-2 text-sm"
      />
    </div>
  );
}
