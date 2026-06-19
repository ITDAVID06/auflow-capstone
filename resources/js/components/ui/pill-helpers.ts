/**
 * Tone mappings for status and role pills
 */

export const toneByStatus = {
  active: 'green',
  inactive: 'gray',
  archive: 'red',
  archived: 'red',
  pending: 'yellow',
  suspended: 'orange',
} as const;

/**
 * ENHANCED: Diversified role colors based on role type
 */
export const toneByRole = {
  // Admin roles - Purple/Violet (authority)
  admin: 'purple',
  administrator: 'purple',
  'super admin': 'purple',
  'system admin': 'purple',
  
  // Approver roles - Blue (trustworthy)
  approver: 'blue',
  reviewer: 'blue',
  manager: 'blue',
  supervisor: 'blue',
  
  // Verifier roles - Cyan/Teal (validation)
  verifier: 'cyan',
  validator: 'cyan',
  checker: 'cyan',
  auditor: 'teal',
  
  // Requester roles - Indigo (user-focused)
  requester: 'indigo',
  student: 'indigo',
  applicant: 'indigo',
  
  // Staff roles - Slate (professional)
  staff: 'slate',
  employee: 'slate',
  
  // Editor/Content roles - Amber (creative)
  editor: 'amber',
  'content manager': 'amber',
  
  // Finance/Accounting - Emerald (money)
  'finance officer': 'emerald',
  accountant: 'emerald',
  cashier: 'emerald',
  
  // Default fallback
  default: 'blue',
} as const;