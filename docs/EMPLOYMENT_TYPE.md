# Employment Types and Governance

This system distinguishes between **Regular** and **Part-time** workers. Employment type drives entitlement and rules for **Cash Advance**, **paid leave**, **benefits**, and **payroll**. Policies are aligned with the project System Document (docs/System Document.pdf) and with typical practices in Philippine construction companies.

## 1. Employment Types

- **regular**
  - Typically full-time, long-term workers assigned to projects or head office.
  - Eligible for standard statutory benefits (SSS, PhilHealth, Pag-IBIG) and company benefits as defined by policy.
  - Eligible for Cash Advance within configured caps.
  - Accrues paid leave according to company rules.

- **part_time**
  - Project-based, short-term, or less-than-full-time workers.
  - May receive statutory benefits according to law and company policy but usually **not** eligible for the same level of company benefits as regular employees.
  - By default **not eligible** for Cash Advance, or eligible only under lower caps and with explicit override.
  - Leave days are treated as unpaid unless policy or override states otherwise.

The `users.employment_type` column stores one of these values (`regular` or `part_time`).

## 2. Governance Model for Changes

Employment type is **not** edited freely. Changes follow a controlled workflow to ensure auditability and correct payroll/benefit behavior.

- **HR**
  - Initiates employment-type change requests.
  - Cannot approve their own requests.

- **Manager**
  - Operational approver for staff within their remit (project/team/crew).
  - Validates the business justification.

- **Admin (Owner)**
  - May override in exceptional cases.
  - Must supply a clear reason when overriding.

- **Supervisor / Worker / Superadmin (dev/ops)**
  - Have **no authority** to change employment type.

All changes are logged with:

- Who requested/approved/overrode.
- When actions occurred.
- Before/after snapshots of the user record.
- Reasons for the change or override.

## 3. Business Rules (Summary)

- **No self-approval**
  - The requester and the target user cannot approve the change.

- **Domain validation**
  - Managers may approve only for users they are responsible for; otherwise approvals escalate to Admin.

- **Effect timing**
  - Cash Advance eligibility, leave accruals, and payroll rules change only when the request is **approved** (or **overridden** by Admin).
  - Pending requests do **not** affect current payroll or benefits.

- **Reversals**
  - Reverting an employment type uses the same HR → Manager workflow.
  - Admin override is allowed but always logged.

- **Immutability**
  - Employment-type change requests are not hard-deleted. History is preserved for audit and compliance.

## 4. Impact on Core Modules

- **Cash Advance (CA)**
  - Default: only `regular` workers are eligible to request CA.
  - If company policy permits CA for `part_time`, lower caps are configured in `config/payroll.php` (e.g. `ca.cap.regular`, `ca.cap.part_time`).
  - Overrides (e.g. allowing CA for a part-time worker above caps) are possible for Managers/Admin and are logged.

- **Leave**
  - Regular employees accrue paid leave as per company rules.
  - Part-time employees may have pro-rated or no accrual and are typically treated as unpaid leave days.

- **Payroll & Benefits**
  - When computing payroll, employment_type influences:
    - Which benefits and deductions apply.
    - How paid leave is handled.
    - Any company-specific allowances for regular vs part-time.

- **Reporting & Audit**
  - Headcount, CA aging, leave liabilities, and payroll reports include employment_type and can be filtered by type.
  - Employment-type changes and overrides are visible in audit and analytics views.

## 5. One-line Policy Summary

> Employment type changes are initiated by HR, approved by the Manager, and can be overridden by Admin only in exceptional cases; all changes are logged immutably and take effect only after approval to ensure auditability and correct payroll/benefit behavior.
