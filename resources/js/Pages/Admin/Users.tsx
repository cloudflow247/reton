import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import { AdminLayout } from '@/components/AdminLayout'
import { Button, Card, Field, Pill } from '@/components/ui'
import { buildAdminUrl, useAdminBase } from '@/lib/admin'
import { shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type UserRow = {
  id: string
  name: string
  email: string
  phone: string | null
  status: 'active' | 'suspended' | 'frozen'
  is_admin: boolean
  email_verified: boolean
  last_login_at: string | null
  created_at: string | null
}

type StatusOption = { value: string; label: string }

type UsersProps = PageProps<{
  users: {
    data: UserRow[]
    links: { url: string | null; label: string; active: boolean }[]
    total: number
    current_page: number
    last_page: number
  }
  filters: { q: string }
  statusOptions: StatusOption[]
}>

const emptyCreate = {
  name: '',
  email: '',
  phone: '',
  password: '',
  status: 'active',
  is_admin: false,
}

export default function Users() {
  const { users, filters, statusOptions, flash, errors } = usePage<UsersProps>().props
  const adminBase = useAdminBase()
  const [search, setSearch] = useState(filters.q)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<UserRow | null>(null)

  const createForm = useForm(emptyCreate)
  const editForm = useForm({
    name: '',
    phone: '',
    status: 'active',
    is_admin: false,
  })

  function runSearch(e: React.FormEvent) {
    e.preventDefault()
    router.get(buildAdminUrl(adminBase, 'users'), { q: search || undefined }, { preserveState: true, replace: true })
  }

  function openEdit(user: UserRow) {
    setEditing(user)
    editForm.setData({
      name: user.name,
      phone: user.phone ?? '',
      status: user.status,
      is_admin: user.is_admin,
    })
  }

  function submitCreate(e: React.FormEvent) {
    e.preventDefault()
    createForm.post(buildAdminUrl(adminBase, 'users'), {
      preserveScroll: true,
      onSuccess: () => {
        createForm.reset()
        setCreating(false)
      },
    })
  }

  function submitEdit(e: React.FormEvent) {
    e.preventDefault()
    if (!editing) return
    editForm.put(buildAdminUrl(adminBase, `users/${editing.id}`), {
      preserveScroll: true,
      onSuccess: () => setEditing(null),
    })
  }

  function removeUser(user: UserRow) {
    if (
      !window.confirm(
        `Remove ${user.email}? Login access will be revoked immediately and personal details anonymized. Financial records are kept for compliance.`,
      )
    ) {
      return
    }

    router.delete(buildAdminUrl(adminBase, `users/${user.id}`), {
      preserveScroll: true,
      onSuccess: () => setEditing(null),
    })
  }

  function rebindProviderEmail(user: UserRow) {
    if (
      !window.confirm(
        `Move Wema/ALATPay bank alerts for ${user.email} onto the Reton merchant (CEO) inbox? The customer will only receive Reton alerts.`,
      )
    ) {
      return
    }

    router.post(buildAdminUrl(adminBase, `users/${user.id}/rebind-provider-email`), {}, { preserveScroll: true })
  }

  const actionError =
    (typeof errors.user === 'string' ? errors.user : errors.user?.[0]) ??
    (typeof errors.email === 'string' ? errors.email : errors.email?.[0])

  const statusTone = (status: UserRow['status']) => {
    if (status === 'active') return 'mint' as const
    if (status === 'suspended') return 'amber' as const
    return 'danger' as const
  }

  return (
    <AdminLayout>
      <Head title="Users" />

      <div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="font-display text-2xl font-bold tracking-tight">Users</h1>
            <p className="mt-1 text-sm text-muted">
              Manage accounts, roles, and access — {users.total.toLocaleString()} total.
            </p>
          </div>
          <Button type="button" onClick={() => setCreating((v) => !v)}>
            {creating ? 'Cancel' : 'Add user'}
          </Button>
        </div>

        {flash.success && (
          <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
        )}
        {flash.error && (
          <p className="rounded-xl border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
        )}
        {actionError && (
          <p className="rounded-xl border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">{actionError}</p>
        )}

        {creating && (
          <Card className="space-y-4 p-5">
            <h2 className="font-display text-lg font-semibold">Create user</h2>
            <form onSubmit={submitCreate} className="grid gap-4 sm:grid-cols-2">
              <Field label="Full name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} />
              <Field label="Email" type="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} />
              <Field label="Phone" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} error={createForm.errors.phone} />
              <Field label="Password" type="password" value={createForm.data.password} onChange={(e) => createForm.setData('password', e.target.value)} error={createForm.errors.password} />
              <label className="block text-sm">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Status</span>
                <select className="field w-full px-3 py-2.5 text-sm" value={createForm.data.status} onChange={(e) => createForm.setData('status', e.target.value)}>
                  {statusOptions.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </select>
              </label>
              <label className="flex items-center gap-2 self-end pb-2 text-sm">
                <input type="checkbox" checked={createForm.data.is_admin} onChange={(e) => createForm.setData('is_admin', e.target.checked)} />
                Platform administrator
              </label>
              <div className="sm:col-span-2">
                <Button type="submit" loading={createForm.processing}>Create user</Button>
              </div>
            </form>
          </Card>
        )}

        {editing && (
          <Card className="space-y-4 p-5">
            <h2 className="font-display text-lg font-semibold">Edit {editing.email}</h2>
            <form onSubmit={submitEdit} className="grid gap-4 sm:grid-cols-2">
              <Field label="Full name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} error={editForm.errors.name} />
              <Field label="Phone" value={editForm.data.phone} onChange={(e) => editForm.setData('phone', e.target.value)} error={editForm.errors.phone} />
              <label className="block text-sm">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Status</span>
                <select className="field w-full px-3 py-2.5 text-sm" value={editForm.data.status} onChange={(e) => editForm.setData('status', e.target.value)}>
                  {statusOptions.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </select>
                {editForm.errors.status && <p className="mt-1 text-sm text-danger">{editForm.errors.status}</p>}
              </label>
              <label className="flex items-center gap-2 self-end pb-2 text-sm">
                <input type="checkbox" checked={editForm.data.is_admin} onChange={(e) => editForm.setData('is_admin', e.target.checked)} />
                Platform administrator
                {editForm.errors.is_admin && <span className="text-danger">{editForm.errors.is_admin}</span>}
              </label>
              <div className="flex flex-wrap gap-2 sm:col-span-2">
                <Button type="submit" loading={editForm.processing}>Save changes</Button>
                <Button type="button" variant="ghost" onClick={() => setEditing(null)}>Cancel</Button>
              </div>
            </form>
          </Card>
        )}

        <Card className="overflow-hidden p-0">
          <form onSubmit={runSearch} className="flex flex-wrap items-center gap-2 border-b border-line p-4">
            <input
              className="field min-w-[200px] flex-1 px-3 py-2 text-sm"
              placeholder="Search name, email, or phone…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <Button type="submit" variant="ghost">Search</Button>
          </form>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-4 py-3 font-semibold">User</th>
                  <th className="px-4 py-3 font-semibold">Status</th>
                  <th className="px-4 py-3 font-semibold">Role</th>
                  <th className="px-4 py-3 font-semibold">Last login</th>
                  <th className="px-4 py-3 font-semibold">Joined</th>
                  <th className="px-4 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.data.map((user) => (
                  <tr key={user.id} className="border-b border-line/80 last:border-0">
                    <td className="px-4 py-3">
                      <div className="font-semibold text-text">{user.name}</div>
                      <div className="text-xs text-muted">{user.email}</div>
                      {user.phone && <div className="text-xs text-muted">{user.phone}</div>}
                    </td>
                    <td className="px-4 py-3">
                      <Pill tone={statusTone(user.status)}>{user.status}</Pill>
                    </td>
                    <td className="px-4 py-3">
                      {user.is_admin ? <Pill tone="mint">Admin</Pill> : <span className="text-muted">Customer</span>}
                      {!user.email_verified && <div className="mt-1 text-xs text-amber">Unverified email</div>}
                    </td>
                    <td className="px-4 py-3 text-muted">{user.last_login_at ? shortDate(user.last_login_at) : '—'}</td>
                    <td className="px-4 py-3 text-muted">{user.created_at ? shortDate(user.created_at) : '—'}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-2">
                        <button type="button" className="text-xs font-semibold text-mint hover:underline" onClick={() => openEdit(user)}>
                          Edit
                        </button>
                        <button
                          type="button"
                          className="text-xs font-semibold text-mint hover:underline"
                          onClick={() => rebindProviderEmail(user)}
                          title="Move Wema bank alerts to the CEO merchant inbox"
                        >
                          Rebind alerts
                        </button>
                        <button type="button" className="text-xs font-semibold text-danger hover:underline" onClick={() => removeUser(user)}>
                          Remove
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {users.data.length === 0 && (
            <p className="p-8 text-center text-sm text-muted">No users match your search.</p>
          )}

          {users.links.length > 3 && (
            <div className="flex flex-wrap gap-1 border-t border-line p-3">
              {users.links.map((link, i) => (
                <button
                  key={`${link.label}-${i}`}
                  type="button"
                  disabled={!link.url}
                  onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                  className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${
                    link.active ? 'bg-mint/12 text-mint' : 'text-muted hover:bg-surface-2'
                  }`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          )}
        </Card>
      </div>
    </AdminLayout>
  )
}
