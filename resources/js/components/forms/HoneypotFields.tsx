import type { InputHTMLAttributes } from 'react'

/** Invisible trap fields for bot registrations / logins. */
export function HoneypotFields({
  websiteProps,
}: {
  websiteProps?: InputHTMLAttributes<HTMLInputElement>
} = {}) {
  return (
    <div
      aria-hidden="true"
      className="pointer-events-none absolute -left-[9999px] h-0 w-0 overflow-hidden opacity-0"
    >
      <label>
        Website
        <input type="text" tabIndex={-1} autoComplete="off" {...websiteProps} />
      </label>
      <label>
        Company URL
        <input type="url" name="company_url" tabIndex={-1} autoComplete="off" defaultValue="" />
      </label>
      <label>
        Fax
        <input type="text" name="fax_number" tabIndex={-1} autoComplete="off" defaultValue="" />
      </label>
    </div>
  )
}
