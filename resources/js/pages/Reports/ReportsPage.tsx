import React, { useCallback, useState } from "react";
import { router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { FormPicker } from "./components/FormPicker";
import { OverviewTab } from "./tabs/OverviewTab";
import { DataTab } from "./tabs/DataTab";
import { ExportsTab } from "./tabs/ExportsTab";
import { CompareTab } from "./tabs/CompareTab";
import { ReportFiltersState, ReportTab } from "./types";

interface Props {
  error?: string | null;
}

// Read initial form_id and tab from URL query params (Inertia preserves the URL)
const getInitialFormId = (): number | null => {
  const params = new URLSearchParams(window.location.search);
  const v = params.get("form_id");
  return v ? Number(v) : null;
};

const getInitialTab = (): ReportTab => {
  const params = new URLSearchParams(window.location.search);
  const t = params.get("tab");
  return (["overview", "data", "exports", "compare"] as ReportTab[]).includes(t as ReportTab)
    ? (t as ReportTab)
    : "overview";
};

const ReportsPage: React.FC<Props> = ({ error }) => {
  const [formId, setFormId] = useState<number | null>(getInitialFormId);
  const [activeTab, setActiveTab] = useState<ReportTab>(getInitialTab);
  const [asyncExportId, setAsyncExportId] = useState<string | null>(null);
  const [dataTabFilters, setDataTabFilters] = useState<ReportFiltersState | null>(null);
  const [pendingDateOverride, setPendingDateOverride] = useState<{ date_from: string; date_to: string } | null>(null);

  // Keep URL in sync so tabs/form are shareable
  const updateUrl = useCallback((newFormId: number | null, newTab: ReportTab) => {
    const params = new URLSearchParams();
    if (newFormId) params.set("form_id", String(newFormId));
    params.set("tab", newTab);
    router.get(route("reports.index") + "?" + params.toString(), {}, { preserveState: true, preserveScroll: true, replace: true });
  }, []);

  const handleFormChange = (id: number | null) => {
    setFormId(id);
    setActiveTab("overview");
    setAsyncExportId(null);
    updateUrl(id, "overview");
  };

  const handleTabChange = (tab: string) => {
    const t = tab as ReportTab;
    setActiveTab(t);
    updateUrl(formId, t);
  };

  const handleAsyncExport = (exportId: string) => {
    setAsyncExportId(exportId);
    handleTabChange("exports");
  };

  const handleNavigateToData = (dateFrom: string, dateTo: string) => {
    setPendingDateOverride({ date_from: dateFrom, date_to: dateTo });
    handleTabChange("data");
  };

  return (
    <AppLayout>
      <div className="flex flex-col gap-6 p-6 max-w-[1600px] mx-auto w-full">
        {/* Header */}
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold">Reports</h1>
          <FormPicker selectedFormId={formId} onChange={handleFormChange} />
        </div>

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {!formId ? (
          <div className="flex items-center justify-center py-24 text-muted-foreground">
            Select a form above to get started.
          </div>
        ) : (
          <Tabs value={activeTab} onValueChange={handleTabChange}>
            <TabsList>
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="data">Data</TabsTrigger>
              <TabsTrigger value="exports">
                Exports
                {asyncExportId && (
                  <span className="ml-1.5 h-2 w-2 rounded-full bg-primary inline-block" />
                )}
              </TabsTrigger>
              <TabsTrigger value="compare">Compare</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="mt-4">
              <OverviewTab formId={formId} onNavigateToData={handleNavigateToData} />
            </TabsContent>

            <TabsContent value="data" className="mt-4">
              <DataTab
                formId={formId}
                onAsyncExport={handleAsyncExport}
                onFiltersChange={(f) => {
                  setDataTabFilters(f);
                  setPendingDateOverride(null);
                }}
                filterOverride={pendingDateOverride}
              />
            </TabsContent>

            <TabsContent value="exports" className="mt-4">
              <ExportsTab
                formId={formId}
                activeExportId={asyncExportId}
                onExportIdChange={setAsyncExportId}
                dataTabFilters={dataTabFilters}
              />
            </TabsContent>

            <TabsContent value="compare" className="mt-4">
              <CompareTab formId={formId} />
            </TabsContent>
          </Tabs>
        )}
      </div>
    </AppLayout>
  );
};

export default ReportsPage;
