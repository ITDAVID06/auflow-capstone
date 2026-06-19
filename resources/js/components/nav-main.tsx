import { Link } from "@inertiajs/react";
import type { NavItem } from "@/types";
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";

type NavMainProps = {
  items: NavItem[];
  /** Optional section label. If omitted, no label is shown. */
  label?: string | null;
  /** Reduce paddings/gaps for a denser sidebar. */
  density?: "default" | "compact";
};

export function NavMain({ items, label, density = "default" }: NavMainProps) {
  const compact = density === "compact";

  return (
    <SidebarGroup className={compact ? "py-0" : undefined}>
      {label ? (
        <SidebarGroupLabel className={compact ? "px-3 pt-2 pb-1 text-[10px]" : undefined}>
          {label}
        </SidebarGroupLabel>
      ) : null}

      <SidebarGroupContent>
        <SidebarMenu className={compact ? "gap-0" : undefined}>
          {items.map((item) => {
            const Icon = item.icon as any;
            return (
              <SidebarMenuItem key={item.href} className={compact ? "py-0" : undefined}>
                <SidebarMenuButton asChild className={compact ? "h-8 px-2 text-sm" : undefined}>
                  <Link href={item.href} prefetch>
                    {Icon ? <Icon className="h-[18px] w-[18px]" /> : null}
                    <span>{item.title}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            );
          })}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  );
}
