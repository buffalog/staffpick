"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { postCaseMessage } from "./actions";

type Message = {
  id: string;
  body: string;
  senderEmail: string;
  senderName: string | null;
  createdAt: string;
};

type Props = {
  requestId: string;
  initialMessages: Message[];
  currentUserEmail: string;
};

const POLL_INTERVAL_MS = 10_000;

export function MessageThread({
  requestId,
  initialMessages,
  currentUserEmail,
}: Props) {
  const [messages, setMessages] = useState<Message[]>(initialMessages);
  const [pending, startTransition] = useTransition();
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let cancelled = false;
    async function refresh() {
      try {
        const res = await fetch(`/api/cases/${requestId}/messages`, {
          cache: "no-store",
        });
        if (!res.ok) return;
        const json = (await res.json()) as { messages: Message[] };
        if (cancelled) return;
        setMessages((prev) => {
          if (prev.length === json.messages.length) return prev;
          return json.messages;
        });
      } catch {
        // network blip — swallow
      }
    }
    const id = setInterval(refresh, POLL_INTERVAL_MS);
    return () => {
      cancelled = true;
      clearInterval(id);
    };
  }, [requestId]);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
  }, [messages.length]);

  async function handleSubmit(formData: FormData) {
    const body = String(formData.get("body") ?? "").trim();
    if (!body) return;
    startTransition(async () => {
      await postCaseMessage(requestId, formData);
      if (textareaRef.current) textareaRef.current.value = "";
      // Optimistic refetch
      try {
        const res = await fetch(`/api/cases/${requestId}/messages`, {
          cache: "no-store",
        });
        if (res.ok) {
          const json = (await res.json()) as { messages: Message[] };
          setMessages(json.messages);
        }
      } catch {
        // ignore
      }
    });
  }

  return (
    <div className="space-y-3">
      <div
        ref={scrollRef}
        className="max-h-80 overflow-auto rounded-md border bg-muted/20 p-3 space-y-3 text-sm"
      >
        {messages.length === 0 ? (
          <p className="text-muted-foreground text-center py-6">
            No messages yet — start the conversation.
          </p>
        ) : (
          messages.map((m) => {
            const isMine = m.senderEmail === currentUserEmail;
            return (
              <div
                key={m.id}
                className={`flex flex-col ${isMine ? "items-end" : "items-start"}`}
              >
                <div className="text-xs text-muted-foreground mb-1">
                  {m.senderName ?? m.senderEmail} · {new Date(m.createdAt).toLocaleString()}
                </div>
                <div
                  className={`rounded-md px-3 py-2 max-w-[80%] whitespace-pre-wrap ${
                    isMine
                      ? "bg-primary text-primary-foreground"
                      : "bg-card border"
                  }`}
                >
                  {m.body}
                </div>
              </div>
            );
          })
        )}
      </div>
      <form action={handleSubmit} className="flex gap-2 items-start">
        <textarea
          ref={textareaRef}
          name="body"
          rows={2}
          placeholder="Write a message…"
          required
          maxLength={4000}
          className="flex-1 rounded-md border bg-background px-3 py-2 text-sm resize-y"
        />
        <button
          type="submit"
          disabled={pending}
          className="rounded-md bg-primary text-primary-foreground px-3 py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
        >
          {pending ? "Sending…" : "Send"}
        </button>
      </form>
    </div>
  );
}
