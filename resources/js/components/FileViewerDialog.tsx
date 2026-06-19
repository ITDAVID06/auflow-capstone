import * as React from "react";
import { useState, useEffect, useRef } from "react";
import { 
  Dialog, 
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { 
  ExternalLink, 
  Download, 
  X, 
  ZoomIn, 
  ZoomOut, 
  RotateCw, 
  Maximize2
} from "lucide-react";
import * as DialogPrimitive from "@radix-ui/react-dialog";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  url: string;
  title?: string;
  mime?: string;
};

export default function FileViewerDialog({ open, onOpenChange, url, title, mime }: Props) {
  const [zoom, setZoom] = useState(100);
  const [rotation, setRotation] = useState(0);
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [isDragging, setIsDragging] = useState(false);
  const [dragStart, setDragStart] = useState({ x: 0, y: 0 });
  const [imgDimensions, setImgDimensions] = useState<{ width: number; height: number } | null>(null);
  const [textPreview, setTextPreview] = useState<string | null>(null);
  const [textLoading, setTextLoading] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const extension = React.useMemo(() => {
    const cleanUrl = url.split("?")[0].split("#")[0];
    const match = cleanUrl.match(/\.([a-z0-9]+)$/i);
    return match ? match[1].toLowerCase() : "";
  }, [url]);

  const isImage = mime?.startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|bmp|svg|tiff|tif|avif)$/i.test(url);
  const isPdf = mime?.includes("pdf") || extension === "pdf";
  const isTextLike =
    mime?.startsWith("text/") ||
    ["txt", "csv", "json", "xml", "md", "log", "sql", "yaml", "yml"].includes(extension);
  const isOfficeDoc =
    ["doc", "docx", "xls", "xlsx", "ppt", "pptx", "odt", "ods", "odp", "rtf"].includes(extension) ||
    /officedocument|msword|excel|powerpoint|opendocument|rtf/i.test(mime || "");

  const absoluteUrl = React.useMemo(() => {
    if (!url) return "";
    if (url.startsWith("http://") || url.startsWith("https://")) {
      return url;
    }

    if (typeof window !== "undefined") {
      return new URL(url, window.location.origin).toString();
    }

    return url;
  }, [url]);

  const isLocalhostUrl = React.useMemo(() => {
    if (!absoluteUrl) return false;

    try {
      const parsed = new URL(absoluteUrl);
      return ["localhost", "127.0.0.1", "::1"].includes(parsed.hostname);
    } catch {
      return false;
    }
  }, [absoluteUrl]);

  const officeViewerUrl = React.useMemo(() => {
    if (!absoluteUrl) return "";
    return `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(absoluteUrl)}`;
  }, [absoluteUrl]);

  // Calculate optimal initial zoom
  useEffect(() => {
    if (!open || !isImage) return;

    const img = new Image();
    img.src = url;
    
    img.onload = () => {
      const naturalWidth = img.naturalWidth;
      const naturalHeight = img.naturalHeight;
      setImgDimensions({ width: naturalWidth, height: naturalHeight });
      
      // Calculate available viewport space
      const viewportWidth = window.innerWidth * 0.9;
      const viewportHeight = window.innerHeight * 0.85;
      
      // Calculate scale to fit nicely (not too small)
      const scaleWidth = viewportWidth / naturalWidth;
      const scaleHeight = viewportHeight / naturalHeight;
      const scale = Math.min(scaleWidth, scaleHeight);
      
      // Set zoom: minimum 50% for readability, max 100% to avoid upscaling
      const optimalZoom = Math.max(50, Math.min(100, Math.floor(scale * 100)));
      setZoom(optimalZoom);
    };
  }, [open, url, isImage]);

  // Reset on close
  useEffect(() => {
    if (!open) {
      setZoom(100);
      setRotation(0);
      setPosition({ x: 0, y: 0 });
      setImgDimensions(null);
      setIsDragging(false);
      setTextPreview(null);
      setTextLoading(false);
    }
  }, [open]);

  useEffect(() => {
    if (!open || !isTextLike || !url) return;

    const controller = new AbortController();
    setTextLoading(true);

    fetch(url, { signal: controller.signal, credentials: "include" })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error("Unable to preview this file");
        }

        const text = await response.text();
        const preview = text.length > 50000 ? `${text.slice(0, 50000)}\n\n… (truncated)` : text;
        setTextPreview(preview);
      })
      .catch(() => {
        setTextPreview(null);
      })
      .finally(() => {
        setTextLoading(false);
      });

    return () => controller.abort();
  }, [open, isTextLike, url]);

  // Handlers
  const handleZoomIn = () => setZoom((prev) => Math.min(prev + 25, 300));
  const handleZoomOut = () => setZoom((prev) => Math.max(prev - 25, 25));
  const handleRotate = () => setRotation((prev) => (prev + 90) % 360);
  
  const handleFitToScreen = () => {
    if (!imgDimensions) return;
    const viewportWidth = window.innerWidth * 0.9;
    const viewportHeight = window.innerHeight * 0.85;
    const scaleWidth = viewportWidth / imgDimensions.width;
    const scaleHeight = viewportHeight / imgDimensions.height;
    const scale = Math.min(scaleWidth, scaleHeight);
    setZoom(Math.max(25, Math.min(100, Math.floor(scale * 100))));
    setRotation(0);
    setPosition({ x: 0, y: 0 });
  };

  const handleActualSize = () => {
    setZoom(100);
    setRotation(0);
    setPosition({ x: 0, y: 0 });
  };

  // Drag handlers
  const handleMouseDown = (e: React.MouseEvent) => {
    if (!isImage || zoom <= 100) return;
    e.preventDefault();
    setIsDragging(true);
    setDragStart({ x: e.clientX - position.x, y: e.clientY - position.y });
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!isDragging) return;
    setPosition({ x: e.clientX - dragStart.x, y: e.clientY - dragStart.y });
  };

  const handleMouseUp = () => setIsDragging(false);

  // Keyboard shortcuts
  useEffect(() => {
    if (!open) return;

    const handleKey = (e: KeyboardEvent) => {
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;
      
      switch (e.key) {
        case "+":
        case "=":
          e.preventDefault();
          setZoom((prev) => Math.min(prev + 25, 300));
          break;
        case "-":
          e.preventDefault();
          setZoom((prev) => Math.max(prev - 25, 25));
          break;
        case "r":
        case "R":
          e.preventDefault();
          setRotation((prev) => (prev + 90) % 360);
          break;
        case "0":
          e.preventDefault();
          if (imgDimensions) {
            const viewportWidth = window.innerWidth * 0.9;
            const viewportHeight = window.innerHeight * 0.85;
            const scaleWidth = viewportWidth / imgDimensions.width;
            const scaleHeight = viewportHeight / imgDimensions.height;
            const scale = Math.min(scaleWidth, scaleHeight);
            setZoom(Math.max(25, Math.min(100, Math.floor(scale * 100))));
            setRotation(0);
            setPosition({ x: 0, y: 0 });
          }
          break;
        case "1":
          e.preventDefault();
          setZoom(100);
          setRotation(0);
          setPosition({ x: 0, y: 0 });
          break;
        case "Escape":
          onOpenChange(false);
          break;
      }
    };

    window.addEventListener("keydown", handleKey);
    return () => window.removeEventListener("keydown", handleKey);
  }, [open, imgDimensions, onOpenChange]);

  // Mouse wheel zoom
  useEffect(() => {
    if (!open || !isImage) return;

    const handleWheel = (e: WheelEvent) => {
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -10 : 10;
        setZoom((prev) => Math.max(25, Math.min(300, prev + delta)));
      }
    };

    window.addEventListener("wheel", handleWheel, { passive: false });
    return () => window.removeEventListener("wheel", handleWheel);
  }, [open, isImage]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
        
        <DialogPrimitive.Content
          className="fixed left-[50%] top-[50%] z-50 translate-x-[-50%] translate-y-[-50%] bg-background shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] rounded-lg overflow-hidden"
          style={{
            width: isImage 
              ? "auto"
              : isPdf 
                ? "90vw" 
                : "600px",
            maxWidth: "95vw",
            height: isImage 
              ? "auto" 
              : isPdf 
                ? "90vh" 
                : "auto",
            maxHeight: "95vh",
          }}
        >
          {/* Header - Fixed at top */}
          <div className="flex items-center justify-between gap-4 px-4 py-3 border-b bg-muted/30 sticky top-0 z-10">
            <h2 className="text-sm font-semibold truncate flex-1">
              {title || "Preview"}
            </h2>
            
            {/* Toolbar */}
            <div className="flex items-center gap-1 flex-shrink-0">
              {isImage && (
                <>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleZoomOut}
                    disabled={zoom <= 25}
                    title="Zoom Out (-)"
                    className="h-8 w-8 p-0"
                  >
                    <ZoomOut className="h-4 w-4" />
                  </Button>
                  
                  <span className="text-xs text-muted-foreground min-w-[3rem] text-center font-mono">
                    {zoom}%
                  </span>
                  
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleZoomIn}
                    disabled={zoom >= 300}
                    title="Zoom In (+)"
                    className="h-8 w-8 p-0"
                  >
                    <ZoomIn className="h-4 w-4" />
                  </Button>
                  
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleRotate}
                    title="Rotate (R)"
                    className="h-8 w-8 p-0"
                  >
                    <RotateCw className="h-4 w-4" />
                  </Button>
                  
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleFitToScreen}
                    title="Fit to Screen (0)"
                    className="h-8 px-2 text-xs"
                  >
                    <Maximize2 className="h-3 w-3 mr-1" />
                    Fit
                  </Button>
                  
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleActualSize}
                    title="100% (1)"
                    className="h-8 px-2 text-xs"
                  >
                    1:1
                  </Button>
                  
                  <div className="w-px h-6 bg-border mx-1" />
                </>
              )}
              
              <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                title="Open in New Tab"
                className="inline-flex items-center justify-center rounded-md hover:bg-accent transition-colors h-8 w-8"
              >
                <ExternalLink className="h-4 w-4" />
              </a>
              
              <a
                href={url}
                download
                title="Download"
                className="inline-flex items-center justify-center rounded-md hover:bg-accent transition-colors h-8 w-8"
              >
                <Download className="h-4 w-4" />
              </a>
              
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onOpenChange(false)}
                title="Close (Esc)"
                className="h-8 w-8 p-0"
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          </div>

          {/* Content Area */}
          <div 
            ref={containerRef}
            className="bg-muted/10"
            style={{
              cursor: isImage && zoom > 100 ? (isDragging ? "grabbing" : "grab") : "default",
              userSelect: "none",
            }}
            onMouseDown={handleMouseDown}
            onMouseMove={handleMouseMove}
            onMouseUp={handleMouseUp}
            onMouseLeave={handleMouseUp}
          >
            {isImage ? (
              <div className="flex items-center justify-center p-4">
                <img
                  src={url}
                  alt={title || "Preview"}
                  draggable={false}
                  className="rounded shadow-2xl select-none"
                  style={{
                    transform: `
                      translate(${position.x}px, ${position.y}px)
                      scale(${zoom / 100}) 
                      rotate(${rotation}deg)
                    `,
                    transformOrigin: "center center",
                    transition: isDragging ? "none" : "transform 0.2s ease-out",
                    maxWidth: zoom <= 100 ? "90vw" : "none",
                    maxHeight: zoom <= 100 ? "85vh" : "none",
                    width: imgDimensions && zoom > 100 ? `${imgDimensions.width}px` : "auto",
                    height: imgDimensions && zoom > 100 ? `${imgDimensions.height}px` : "auto",
                  }}
                />
              </div>
            ) : isPdf ? (
              <iframe
                src={`${url}#toolbar=1&navpanes=0&view=FitH`}
                title={title || mime || "Document"}
                className="w-full"
                style={{ 
                  border: "none",
                  height: "calc(90vh - 60px)",
                }}
              />
            ) : isTextLike ? (
              <div className="p-4">
                <div className="rounded-md border border-border/60 bg-background/90 p-3">
                  {textLoading ? (
                    <p className="text-sm text-muted-foreground">Loading preview…</p>
                  ) : textPreview !== null ? (
                    <pre className="max-h-[70vh] overflow-auto whitespace-pre-wrap text-xs leading-relaxed">{textPreview}</pre>
                  ) : (
                    <div className="text-center space-y-3 py-6">
                      <p className="text-sm text-muted-foreground">Preview not available for this text file.</p>
                      <div className="flex gap-2 justify-center">
                        <a
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center gap-2 rounded-md bg-primary text-primary-foreground px-4 py-2 text-sm hover:bg-primary/90"
                        >
                          <ExternalLink className="h-4 w-4" />
                          Open
                        </a>
                        <a
                          href={url}
                          download
                          className="inline-flex items-center gap-2 rounded-md bg-secondary text-secondary-foreground px-4 py-2 text-sm hover:bg-secondary/80"
                        >
                          <Download className="h-4 w-4" />
                          Download
                        </a>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            ) : isOfficeDoc ? (
              isLocalhostUrl ? (
                <div className="flex items-center justify-center p-8">
                  <div className="text-center space-y-4">
                    <div className="flex items-center justify-center w-16 h-16 mx-auto rounded-full bg-muted">
                      <ExternalLink className="h-8 w-8 text-muted-foreground" />
                    </div>
                    <p className="text-sm text-muted-foreground">
                      Embedded preview is not available for local/private office files.
                    </p>
                    <div className="flex gap-2 justify-center">
                      <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 rounded-md bg-primary text-primary-foreground px-4 py-2 text-sm hover:bg-primary/90"
                      >
                        <ExternalLink className="h-4 w-4" />
                        Open
                      </a>
                      <a
                        href={url}
                        download
                        className="inline-flex items-center gap-2 rounded-md bg-secondary text-secondary-foreground px-4 py-2 text-sm hover:bg-secondary/80"
                      >
                        <Download className="h-4 w-4" />
                        Download
                      </a>
                    </div>
                  </div>
                </div>
              ) : (
                <iframe
                  src={officeViewerUrl}
                  title={title || mime || "Document"}
                  className="w-full"
                  style={{
                    border: "none",
                    height: "calc(90vh - 60px)",
                  }}
                />
              )
            ) : (
              <div className="flex items-center justify-center p-8">
                <div className="text-center space-y-4">
                  <div className="flex items-center justify-center w-16 h-16 mx-auto rounded-full bg-muted">
                    <ExternalLink className="h-8 w-8 text-muted-foreground" />
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Preview not available for this file type
                  </p>
                  <div className="flex gap-2 justify-center">
                    <a
                      href={url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 rounded-md bg-primary text-primary-foreground px-4 py-2 text-sm hover:bg-primary/90"
                    >
                      <ExternalLink className="h-4 w-4" />
                      Open
                    </a>
                    <a
                      href={url}
                      download
                      className="inline-flex items-center gap-2 rounded-md bg-secondary text-secondary-foreground px-4 py-2 text-sm hover:bg-secondary/80"
                    >
                      <Download className="h-4 w-4" />
                      Download
                    </a>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Keyboard Help - Only for images */}
          {isImage && (
            <div className="absolute bottom-4 right-4 bg-black/80 text-white text-xs rounded-md px-3 py-2 pointer-events-none backdrop-blur-sm">
              <div className="space-y-0.5">
                <div>Ctrl/⌘ + Scroll: Zoom</div>
                <div>+/−: Zoom In/Out</div>
                <div>R: Rotate</div>
                <div>0: Fit • 1: 100%</div>
              </div>
            </div>
          )}
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </Dialog>
  );
}