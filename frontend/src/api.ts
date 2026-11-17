export type SlotStatus = "available" | "booked" | "past";
export interface Slot {
  id: number;
  starts_at: string;
  ends_at: string;
  status: SlotStatus;
  booking_id: number | null;
}
export interface Schedule {
  data: Slot[];
  timezone: string;
  server_time: string;
}
export interface BookingInput {
  slot_id: number;
  customer_name: string;
  customer_email: string;
}
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}
async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`/api${path}`, {
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      signal: AbortSignal.timeout(15000),
    });
  } catch {
    throw new ApiError(
      0,
      "Не удалось связаться с сервером. Проверьте соединение и повторите действие.",
    );
  }
  if (response.status === 204) return undefined as T;
  const body = await response.json().catch(() => null);
  if (!response.ok)
    throw new ApiError(
      response.status,
      response.status >= 500
        ? "Ошибка сервера. Попробуйте ещё раз."
        : body?.message || "Не удалось выполнить действие.",
      body?.errors,
    );
  if (body === null)
    throw new ApiError(
      0,
      "Сервер вернул некорректный ответ. Повторите действие.",
    );
  return body as T;
}
export const api = {
  slots: () => request<Schedule>("/slots"),
  book: (input: BookingInput) =>
    request<{ id: number }>("/bookings", {
      method: "POST",
      body: JSON.stringify(input),
    }),
  cancel: (id: number) =>
    request<void>(`/bookings/${id}`, { method: "DELETE" }),
};
