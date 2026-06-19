export const MAX_FILE_BYTES = 10 * 1024 * 1024; // 10 MB

export const ACCEPT_EXT = [".jpg", ".jpeg", ".png", ".pdf", ".doc", ".docx"];

export const ACCEPT_MIME = new Set<string>([
  "image/jpeg",
  "image/jpg",
  "image/png",
  "application/pdf",
  "application/msword",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
]);

export const hasAllowedExtension = (filename: string) =>
  ACCEPT_EXT.some((ext) => filename.toLowerCase().endsWith(ext));

export const isDuplicateFile = (a: File, b: File) =>
  a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;
