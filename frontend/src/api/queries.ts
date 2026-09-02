import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import call from './client'
import type {
  AiExplanation,
  AiUsage,
  BaselineState,
  CheckRun,
  DashboardSummary,
  Finding,
  FindingFilters,
  FindingsPage,
  PostSearchResult,
  Settings,
  Target
} from './types'

export const keys = {
  targets: ['targets'] as const,
  findings: (filters: FindingFilters) => ['findings', filters] as const,
  runStatus: ['run-status'] as const,
  dashboard: ['dashboard-summary'] as const,
  settings: ['settings'] as const,
  postSearch: (term: string) => ['post-search', term] as const
}

/** Every mutation that can change findings should refresh these together. */
const useInvalidateAll = () => {
  const client = useQueryClient()

  return () => {
    client.invalidateQueries({ queryKey: ['findings'] })
    client.invalidateQueries({ queryKey: keys.targets })
    client.invalidateQueries({ queryKey: keys.runStatus })
    client.invalidateQueries({ queryKey: keys.dashboard })
    client.invalidateQueries({ queryKey: ['site-status'] })
  }
}

export const useTargets = () =>
  useQuery({ queryKey: keys.targets, queryFn: () => call<Target[]>('targets/get') })

export const useCreateTarget = () => {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (payload: { url?: string; post_id?: number; label?: string }) =>
      call<Target>('targets/create', payload),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.targets })
  })
}

export const useUpdateTarget = () => {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (payload: { id: number; label?: string; is_active?: boolean }) =>
      call<Target>('targets/update', payload),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.targets })
  })
}

export const useDeleteTarget = () => {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => call<{ deleted: number }>('targets/delete', { id }),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.targets })
  })
}

export const usePostSearch = (term: string) =>
  useQuery({
    queryKey: keys.postSearch(term),
    queryFn: () => call<PostSearchResult[]>('targets/post-search', { term }),
    enabled: term.length > 1
  })

export const useFindings = (filters: FindingFilters) =>
  useQuery({
    queryKey: keys.findings(filters),
    queryFn: () => call<FindingsPage>('findings/get', filters)
  })

export const useRunStatus = () =>
  useQuery({
    queryKey: keys.runStatus,
    queryFn: () => call<CheckRun | null>('check/status'),
    refetchInterval: query => (query.state.data?.status === 'running' ? 3000 : false)
  })

export const useCheckNow = () => {
  const invalidateAll = useInvalidateAll()

  return useMutation({
    mutationFn: () => call<CheckRun>('check/now'),
    onSuccess: invalidateAll
  })
}

export const useResolveFinding = () => {
  const invalidateAll = useInvalidateAll()

  return useMutation({
    mutationFn: (payload: { id: number; note: string; action: 'mute' | 'resolve' }) =>
      call<Finding>(`findings/${payload.action}`, { id: payload.id, note: payload.note }),
    onSuccess: invalidateAll
  })
}

export const useReopenFinding = () => {
  const invalidateAll = useInvalidateAll()

  return useMutation({
    mutationFn: (id: number) => call<Finding>('findings/reopen', { id }),
    onSuccess: invalidateAll
  })
}

export const useDashboardSummary = () =>
  useQuery({
    queryKey: keys.dashboard,
    queryFn: () => call<DashboardSummary>('dashboard/summary')
  })

export const useArmBaseline = () => {
  const invalidateAll = useInvalidateAll()

  return useMutation({
    mutationFn: () => call<{ targets: number; baseline: BaselineState }>('baseline/arm'),
    onSuccess: invalidateAll
  })
}

export const useDisarmBaseline = () => {
  const invalidateAll = useInvalidateAll()

  return useMutation({
    mutationFn: () => call<{ baseline: null }>('baseline/disarm'),
    onSuccess: invalidateAll
  })
}

export const useExplainWithAi = () =>
  useMutation({
    mutationFn: (payload: { id: number; refresh?: boolean }) =>
      call<AiExplanation>('ai/explain', payload)
  })

export const useAiUsage = (enabled: boolean) =>
  useQuery({
    queryKey: ['ai-usage'],
    queryFn: () => call<{ usage: AiUsage; cap: number; available: boolean }>('ai/usage'),
    enabled
  })

export const useTestSlack = () =>
  useMutation({
    mutationFn: (url: string) => call<{ delivered: boolean }>('settings/test-slack', { url })
  })

export const useSendReport = () =>
  useMutation({ mutationFn: () => call<{ sent: boolean }>('report/send') })

export const usePreviewReport = () =>
  useMutation({
    mutationFn: () => call<{ html: string; recipients: string[] }>('report/preview')
  })

export const useTestWebhook = () =>
  useMutation({
    mutationFn: (url: string) => call<{ delivered: boolean }>('settings/test-webhook', { url })
  })

export const useSettings = () =>
  useQuery({ queryKey: keys.settings, queryFn: () => call<Settings>('settings/get') })

export const useUpdateSettings = () => {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (payload: Partial<Settings>) => call<Settings>('settings/update', payload),
    onSuccess: data => client.setQueryData(keys.settings, data)
  })
}
