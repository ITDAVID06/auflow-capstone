import { cn } from "@/lib/utils";
import logo from "@/assets/auf_logo.png";

type Props = {
  className?: string;
};

export default function AppLogo({ className }: Props) {
  return (
    <div className={cn("flex items-center gap-3", className)}>
      {/* Logo badge */}
      <div className="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg bg-transparent p-1">
        <img
          src={logo}
          alt="AUFlow Logo"
          className="h-full w-full object-contain"
          draggable={false}
        />
      </div>

      {/* Title */}
      <div className="grid flex-1 text-left">
        <span className="text-[15px] font-semibold leading-tight tracking-tight text-sidebar-foreground">
          AUFlow
        </span>
        <span className="-mt-0.5 text-[10px] font-medium uppercase tracking-[0.18em] text-sidebar-foreground/55">
          Portal
        </span>
      </div>
    </div>
  );
}
