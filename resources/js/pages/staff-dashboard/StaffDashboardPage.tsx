import React, { useState } from "react";
import { usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import QuickActions from "./components/QuickActions";
import { StaffDashboardProps } from "./types/staffDashboardTypes";
import RequestsCards from "./components/RequestsCards";
import { CheckCircle2, UserRound } from "lucide-react";
import { type SharedData } from "@/types";
import DashboardMetrics from "../student-dashboard/components/DashboardMetrics";
import StudentQuickActions from "../student-dashboard/components/QuickActions";
import RecentSubmissionsTable from "../student-dashboard/components/RecentSubmissionsTable";

type ActiveTab = "approvals" | "requests";
type RequestStatus = "all" | "pending" | "approved" | "rejected" | "revision";

const tabs: { id: ActiveTab; label: string; icon: React.ReactNode }[] = [
  {
    id: "approvals",
    label: "My Approvals",
    icon: <CheckCircle2 className="h-4 w-4" />,
  },
  {
    id: "requests",
    label: "My Requests",
    icon: <UserRound className="h-4 w-4" />,
  },
];

export default function StaffDashboardPage() {
  const { requests, pendingContext } = usePage<StaffDashboardProps & SharedData>().props;

  const [activeTab, setActiveTab] = useState<ActiveTab>("approvals");
  const [myRequestsSearch, setMyRequestsSearch] = useState("");
  const [myRequestsStatus, setMyRequestsStatus] = useState<RequestStatus>("all");

  return (
    <AppLayout
      title="Staff Dashboard"
      subtitle="Manage approval requests and track your own submissions"
    >
      {/* ── Tab navigation bar ─────────────────────────────────────── */}
      <div className="sticky top-0 z-20 bg-background/95 backdrop-blur-sm border-b border-border/60">
        <div className="mx-auto w-full max-w-[1600px] px-3 sm:px-4 md:px-6 lg:px-8 overflow-x-auto">
          <nav className="flex items-end min-w-max" role="tablist" aria-label="Dashboard tabs">
            {tabs.map((tab) => {
              const active = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  id={`tab-${tab.id}`}
                  role="tab"
                  onClick={() => setActiveTab(tab.id)}
                  aria-selected={active}
                  aria-controls={`panel-${tab.id}`}
                  className={`
                    relative group flex items-center gap-2 px-4 sm:px-5 py-3 sm:py-3.5 text-sm font-medium
                    border-b-2 transition-colors duration-150 touch-manipulation
                    focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/70 focus-visible:ring-inset
                    ${active
                      ? "border-primary text-primary"
                      : "border-transparent text-muted-foreground hover:text-foreground hover:border-border/60"
                    }
                  `}
                >
                  <span className={`transition-colors duration-150 ${active ? "text-primary" : "text-muted-foreground group-hover:text-foreground"}`}>
                    {tab.icon}
                  </span>
                  {tab.label}
                </button>
              );
            })}
          </nav>
        </div>
      </div>

      {/* ── Tab content ────────────────────────────────────────────── */}
      <div className="mx-auto w-full max-w-[1600px] px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-8 space-y-6 sm:space-y-8">

        {/* My Approvals */}
        {activeTab === "approvals" && (
          <div role="tabpanel" id="panel-approvals" aria-labelledby="tab-approvals" className="space-y-6 sm:space-y-8">
            <div className="motion-safe:animate-in motion-safe:fade-in duration-300">
              <DashboardMetrics metricsEndpoint="/staff-dashboard/approval-metrics" tourId="staff-metrics" />
            </div>

            <div className="motion-safe:animate-in motion-safe:fade-in duration-300">
              <section aria-label="Pending Requests">
                <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                  <div className="px-4 sm:px-5 pt-4 sm:pt-5 pb-3 border-b border-gray-100 dark:border-gray-700/60">
                    <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Pending Requests</h2>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Review and process approval requests assigned to you</p>
                  </div>
                  <div className="px-4 sm:px-5 py-3 border-b border-gray-100 dark:border-gray-700/60">
                    <QuickActions />
                  </div>
                  <div className="px-4 sm:px-5 pb-4 sm:pb-5">
                    <RequestsCards requests={requests} pendingContext={pendingContext} />
                  </div>
                </div>
              </section>
            </div>
          </div>
        )}

        {/* My Requests */}
        {activeTab === "requests" && (
          <div role="tabpanel" id="panel-requests" aria-labelledby="tab-requests" className="space-y-6 sm:space-y-8">
            <div className="motion-safe:animate-in motion-safe:fade-in duration-300">
              <DashboardMetrics metricsEndpoint="/staff-dashboard/metrics" tourId="staff-metrics" />
            </div>

            <div className="motion-safe:animate-in motion-safe:fade-in duration-300">
              <section aria-label="My Submissions">
                <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                  <div className="px-4 sm:px-5 pt-4 sm:pt-5 pb-3 border-b border-gray-100 dark:border-gray-700/60">
                    <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">My Submissions</h2>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">View and manage your document request submissions</p>
                  </div>
                  <div className="px-4 sm:px-5 py-3 border-b border-gray-100 dark:border-gray-700/60">
                    <StudentQuickActions
                      search={myRequestsSearch}
                      status={myRequestsStatus}
                      onSearchChange={setMyRequestsSearch}
                      onStatusChange={setMyRequestsStatus}
                      routeNamespace="staff-dashboard"
                    />
                  </div>
                  <div className="px-4 sm:px-5 pb-4 sm:pb-5">
                    <RecentSubmissionsTable
                      search={myRequestsSearch}
                      status={myRequestsStatus}
                      routeNamespace="staff-dashboard"
                      viewRouteName="staff-dashboard.my-submissions.view"
                      editRouteName="staff-dashboard.my-submissions.edit"
                    />
                  </div>
                </div>
              </section>
            </div>
          </div>
        )}

      </div>
    </AppLayout>
  );
}
