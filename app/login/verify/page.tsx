export default function VerifyRequestPage() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-background px-4">
      <div className="w-full max-w-md space-y-4 text-center">
        <h1 className="text-2xl font-semibold tracking-tight">Check your email</h1>
        <p className="text-sm text-muted-foreground">
          We sent a magic-link sign-in to your inbox. The link expires in 24 hours.
        </p>
        <a href="/login" className="text-sm underline">
          Back to sign in
        </a>
      </div>
    </div>
  );
}
