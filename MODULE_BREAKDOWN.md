# Fees Manager CRM – Module Roadmap

This document captures the agreed implementation sequence. We will ship each module end-to-end, verify it, and then move to the next.

---

## Phase 0 – Foundation & Setup
- Finalize database schema (tables/relationships/indices)
- Prepare baseline seed data (settings, templates) if required
- Ensure clean environment (repo, env, tooling)

## Module 1 – Student Onboarding
- Student intake form (profile, course dropdown, branch dropdown, medium, parent contacts)
- Fee breakup builder with live totals (must equal total fee)
- Installment planner (auto-generate + manual overrides, validation)
- Persistence logic + validation + UID generation
- Student profile view (summary, schedule, contacts)

## Module 2 – Payment Processing (GST Inclusive)
- Payment entry UI (mode, amount, date, transaction ID, remarks)
- GST-inclusive calculation service (split base/GST)
- Apply payments to installments; update balance/reminders
- WhatsApp receipt message trigger + log entry - pending approval - will be handled by a separate module

## Module 3 – Penalty & Reminder Automation
- Configurable grace period and penalty rate (from settings)
- Scheduled task to apply penalties & mark installments overdue
- Reminder scheduling + logging (WhatsApp queue) - pending approval - will be handled by a separate module
- Visibility of outstanding penalties/reminders

## Module 4 – Reschedule Workflow
- Staff request form (reason + new due date, enforce 2-attempt limit)
- Admin approval/rejection views
- Installment adjustments, ledger entries, notifications

## Module 5 – Discount Workflow
- Staff request discount (amount, reason, optional document)
- Admin decision flow; balance adjustments
- WhatsApp notification & ledger entry - pending approval

## Module 6 – WhatsApp Integration Layer
- AiSensy integration service (configurable key/URL) - pending approval
- Template management UI (placeholders, categories)
- Conversation log per student (messages + responses)

## Module 7 – Tax & Safe-Ratio Dashboard
- Cash vs online totals with base & GST breakdown
- Safe ratio alert (online base vs cash base)
- Dashboard widgets/charts for admin view

## Module 8 – Reports & Exports
- Reports: Daily collection, due list, discount approvals, GST summary, penalty log, reschedule log, communication log
- Export capabilities (Excel/PDF)

## Module 9 – Audit & Security
- Audit logging middleware (model/action/user, old/new values)
- OTP verification for sensitive actions
- Archive (soft delete) policy implementation

### Module 10 – Settings & Automation Console #### 
- Admin settings UI for rates, grace periods, reminder cadence, templates, safe ratio, auto-approval limits, export permissions
- Automation status monitor (cron health, queues, reminder jobs)

## Module 11 – Role & Permission Layer ##$##
- Enforce admin vs staff permissions across modules
- Approval gates, restricted exports, etc.
- Optional 2FA/hardening as needed

## Module 12 – Final QA & Hardening 
- End-to-end testing checklist & documentation
- Performance review (indexes, caching as needed)
- Deployment readiness (env config, backup strategy)

---

**Execution approach:** work through modules sequentially, only moving forward after stakeholder review and acceptance of the current module. Testing (automated + manual) will be tied to each module before integration.

