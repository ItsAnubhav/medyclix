-- OPD / IPD Service Posting permissions (Billing group).
-- Inserts categories only — no role grants. Super Admin bypasses RBAC;
-- assign these under Setup → Roles → Assign Permission for other roles.

-- Resolve Billing group id dynamically if needed:
-- SELECT id FROM permission_group WHERE short_code = 'bill' OR name = 'Billing';

INSERT INTO `permission_category`
  (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`, `enable_edit`, `enable_delete`)
SELECT g.id, v.name, v.short_code, 1, 0, 0, 0
FROM `permission_group` g
JOIN (
  SELECT 'OPD Service Posting' AS name, 'opd_service_posting' AS short_code
  UNION ALL SELECT 'OPD Service Posting Edit', 'opd_service_posting_edit'
  UNION ALL SELECT 'OPD Service Posting Save', 'opd_service_posting_save'
  UNION ALL SELECT 'OPD Service Posting Confirm', 'opd_service_posting_confirm'
  UNION ALL SELECT 'IPD Service Posting', 'ipd_service_posting'
  UNION ALL SELECT 'IPD Service Posting Edit', 'ipd_service_posting_edit'
  UNION ALL SELECT 'IPD Service Posting Save', 'ipd_service_posting_save'
  UNION ALL SELECT 'IPD Service Posting Confirm', 'ipd_service_posting_confirm'
) v
WHERE (g.short_code = 'bill' OR g.name = 'Billing')
  AND NOT EXISTS (
    SELECT 1 FROM `permission_category` pc WHERE pc.short_code = v.short_code
  )
LIMIT 8;
