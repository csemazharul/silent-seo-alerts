import { App, Input, Modal, Select, Tabs } from 'antd'
import { useState } from 'react'
import { __ } from '@common/helpers/i18nWrap'
import { useCreateTarget, usePostSearch } from '@/api/queries'

interface Props {
  open: boolean
  onClose: () => void
}

export default function AddTargetModal({ open, onClose }: Props) {
  const { message } = App.useApp()
  const [mode, setMode] = useState('post')
  const [term, setTerm] = useState('')
  const [postId, setPostId] = useState<number | undefined>()
  const [url, setUrl] = useState('')

  const { data: results, isFetching } = usePostSearch(term)
  const createTarget = useCreateTarget()

  const reset = () => {
    setTerm('')
    setPostId(undefined)
    setUrl('')
  }

  const submit = () => {
    const payload = mode === 'post' ? { post_id: postId } : { url }

    if (mode === 'post' && !postId) {
      message.warning(__('Pick a page to monitor.'))

      return
    }

    if (mode === 'url' && url.trim() === '') {
      message.warning(__('Enter a URL on this site.'))

      return
    }

    createTarget.mutate(payload, {
      onSuccess: () => {
        message.success(__('Page added. It will be checked on the next run.'))
        reset()
        onClose()
      },
      onError: error => message.error(error.message)
    })
  }

  return (
    <Modal
      destroyOnClose
      confirmLoading={createTarget.isPending}
      okText={__('Add page')}
      open={open}
      title={__('Add a page to monitor')}
      onCancel={onClose}
      onOk={submit}
    >
      <Tabs
        activeKey={mode}
        onChange={setMode}
        items={[
          {
            key: 'post',
            label: __('Pick a page'),
            children: (
              <Select
                showSearch
                className="w-full"
                filterOption={false}
                loading={isFetching}
                notFoundContent={term.length > 1 ? __('No matches') : __('Type to search')}
                placeholder={__('Search your pages and posts')}
                value={postId}
                onSearch={setTerm}
                onChange={setPostId}
                options={(results ?? []).map(result => ({
                  label: result.title,
                  value: result.post_id
                }))}
              />
            )
          },
          {
            key: 'url',
            label: __('Enter a URL'),
            children: (
              <Input
                placeholder={__('https://example.com/some-page/')}
                value={url}
                onChange={event => setUrl(event.target.value)}
              />
            )
          }
        ]}
      />
    </Modal>
  )
}
