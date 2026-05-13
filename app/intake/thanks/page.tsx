export default function ThanksPage() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-background text-foreground px-4">
      <div className="max-w-md text-center space-y-3">
        <h1 className="text-2xl font-semibold tracking-tight">Referral received</h1>
        <p className="text-sm text-muted-foreground">
          Thanks — our team will review the referral and reach out shortly.
        </p>
        <a href="/intake" className="text-sm underline">
          Submit another referral
        </a>
      </div>
    </div>
  );
}
