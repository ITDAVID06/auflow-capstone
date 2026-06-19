import * as React from "react";
import { Card, CardContent } from "@/components/ui/card";

type DataTableShellProps = {
  children: React.ReactNode;
};

export default function DataTableShell({ children }: DataTableShellProps) {
  return (
    <Card>
      <CardContent className="p-0">
        <div className="overflow-auto">
          {children}
        </div>
      </CardContent>
    </Card>
  );
}
