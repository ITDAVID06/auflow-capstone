import React, { useState } from "react";
import { Head, usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import QuickActions from "./components/QuickActions";
import RecentSubmissionsTable from "./components/RecentSubmissionsTable";
import DashboardMetrics from "./components/DashboardMetrics";

interface PageProps {
  routeNamespace?: "student-dashboard" | "staff-dashboard";
  submissionViewRouteName?: string;
  submissionEditRouteName?: string;
  [key: string]: unknown;
}

export default function StudentDashboardPage() {
  const {
    routeNamespace = "student-dashboard",
    submissionViewRouteName = "student-dashboard.submission.view",
    submissionEditRouteName = "student-dashboard.submission.edit",
  } = usePage<PageProps>().props;

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<"all" | "pending" | "approved" | "rejected" | "revision">("all");

  return (
    <AppLayout
      title="My Submissions"
      subtitle="Track and manage your document requests and approval status"
    >
      <Head title="Student Dashboard" />

      <div className="mx-auto w-full max-w-[1600px] px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-8 space-y-6 sm:space-y-8">
        {/* Metrics */}
        <DashboardMetrics metricsEndpoint={`/${routeNamespace}/metrics`} tourId="student-metrics" />

        {/* Submissions card */}
        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
          {/* Card header */}
          <div className="px-4 sm:px-5 pt-4 sm:pt-5 pb-3 border-b border-gray-100 dark:border-gray-700/60">
            <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">My Submissions</h2>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">View and manage your document request submissions</p>
          </div>

          {/* Toolbar */}
          <div className="px-4 sm:px-5 py-3 border-b border-gray-100 dark:border-gray-700/60">
            <QuickActions
              search={search}
              status={status}
              onSearchChange={setSearch}
              onStatusChange={setStatus}
              routeNamespace={routeNamespace}
            />
          </div>

          {/* Submissions list */}
          <div className="px-4 sm:px-5 pb-4 sm:pb-5">
            <RecentSubmissionsTable
              search={search}
              status={status}
              routeNamespace={routeNamespace}
              viewRouteName={submissionViewRouteName}
              editRouteName={submissionEditRouteName}
              onClearFilters={() => { setSearch(""); setStatus("all"); }}
            />
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
