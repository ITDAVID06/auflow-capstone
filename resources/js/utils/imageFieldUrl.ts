type ImageUrlInput = {
  imageUrl?: string | null;
  imagePath?: string | null;
};

const FORM_IMAGE_STORAGE_PREFIX = "/storage/form_images/";

const toPrivateFilesUrl = (path: string): string => {
  const normalized = path.replace(/^\/+/, "").split("/").filter(Boolean);
  const encoded = normalized.map((segment) => encodeURIComponent(segment)).join("/");

  return `/files/${encoded}`;
};

const extractFormImagesPath = (raw: string): string | null => {
  const value = raw.trim();
  if (!value) {
    return null;
  }

  if (value.startsWith("/files/")) {
    return value.slice("/files/".length).replace(/^\/+/, "") || null;
  }

  if (value.startsWith("form_images/")) {
    return value;
  }

  if (value.startsWith(FORM_IMAGE_STORAGE_PREFIX)) {
    return value.slice(FORM_IMAGE_STORAGE_PREFIX.length - 1).replace(/^\/+/, "");
  }

  try {
    const parsed = new URL(value, typeof window !== "undefined" ? window.location.origin : "http://localhost");
    const pathname = parsed.pathname.replace(/\/+/g, "/");

    if (pathname.startsWith(FORM_IMAGE_STORAGE_PREFIX)) {
      return pathname.slice(FORM_IMAGE_STORAGE_PREFIX.length - 1).replace(/^\/+/, "");
    }

    if (pathname.startsWith("/files/")) {
      return pathname.slice("/files/".length).replace(/^\/+/, "") || null;
    }
  } catch {
    return null;
  }

  return null;
};

export const resolveImageFieldUrl = ({ imageUrl, imagePath }: ImageUrlInput): string => {
  const url = String(imageUrl ?? "").trim();
  const path = String(imagePath ?? "").trim();

  if (url.startsWith("/files/")) {
    return url;
  }

  const extractedFromUrl = extractFormImagesPath(url);
  if (extractedFromUrl) {
    return toPrivateFilesUrl(extractedFromUrl);
  }

  if (path) {
    return toPrivateFilesUrl(path);
  }

  return url;
};
