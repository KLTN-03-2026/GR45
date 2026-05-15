/**
 * Laravel {@see LengthAwarePaginator} JSON trong envelope `{ success, data: pager }`
 * (axiosClient đã trả `response.data`).
 */
export function parseLaravelPaginatorEnvelope(envelope) {
  const pager = envelope?.data;
  if (!pager || typeof pager !== "object") {
    return { rows: [], total: 0, last_page: 1, current_page: 1 };
  }

  const rows = Array.isArray(pager.data) ? pager.data : [];
  const meta =
    pager.meta && typeof pager.meta === "object" ? pager.meta : null;

  const rawTotal = pager.total ?? meta?.total;
  let total = Number(rawTotal);
  if (!Number.isFinite(total)) {
    total = rows.length;
  }

  const lastPage = Math.max(
    1,
    Number(pager.last_page ?? meta?.last_page) || 1,
  );
  const rawCurrent = Number(pager.current_page ?? meta?.current_page) || 1;
  const currentPage = Math.min(Math.max(1, rawCurrent), lastPage);

  return { rows, total, last_page: lastPage, current_page: currentPage };
}
