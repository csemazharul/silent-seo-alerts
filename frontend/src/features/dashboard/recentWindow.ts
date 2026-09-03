/** The dashboard charts share one window over the findings API. */
export const WINDOW_DAYS = 14

/** The last N UTC days as 'YYYY-MM-DD', oldest first — matching created_at. */
export const recentDays = (): string[] =>
  Array.from({ length: WINDOW_DAYS }, (_, i) =>
    new Date(Date.now() - (WINDOW_DAYS - 1 - i) * 86_400_000).toISOString().slice(0, 10)
  )
