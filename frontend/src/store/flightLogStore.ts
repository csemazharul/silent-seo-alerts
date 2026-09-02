import { create } from 'zustand'
import type { FindingFilters, FindingStatus, Severity } from '@/api/types'

interface FlightLogState {
  severity: Severity[]
  status: FindingStatus[]
  targetId?: number
  dateFrom?: string
  dateTo?: string
  page: number
  setSeverity: (severity: Severity[]) => void
  setStatus: (status: FindingStatus[]) => void
  setTargetId: (targetId?: number) => void
  setDateRange: (from?: string, to?: string) => void
  setPage: (page: number) => void
  reset: () => void
  /** Used by the dashboard to deep-link into a filtered log view. */
  showOnly: (severity: Severity[]) => void
}

const initial = {
  severity: [] as Severity[],
  status: ['open'] as FindingStatus[],
  targetId: undefined,
  dateFrom: undefined,
  dateTo: undefined,
  page: 1
}

export const useFlightLogStore = create<FlightLogState>(set => ({
  ...initial,
  setSeverity: severity => set({ severity, page: 1 }),
  setStatus: status => set({ status, page: 1 }),
  setTargetId: targetId => set({ targetId, page: 1 }),
  setDateRange: (dateFrom, dateTo) => set({ dateFrom, dateTo, page: 1 }),
  setPage: page => set({ page }),
  reset: () => set(initial),
  showOnly: severity => set({ ...initial, severity })
}))

export const selectFilters = (state: FlightLogState): FindingFilters => ({
  severity: state.severity.length > 0 ? state.severity : undefined,
  status: state.status.length > 0 ? state.status : undefined,
  target_id: state.targetId,
  date_from: state.dateFrom,
  date_to: state.dateTo,
  page: state.page,
  per_page: 20
})
