import axios from "axios";
import type {
  ExportedWorkflow,
  FormFieldLite,
  FormLite,
  UserLite,
  WorkflowBuilderState,
} from "../types/workflowBuilderTypes";

export async function createWorkflow(data: WorkflowBuilderState) {
  return axios.post("/workflows", data);
}
export async function updateWorkflow(id: number, data: WorkflowBuilderState) {
  return axios.put(`/workflows/${id}`, data);
}
export async function fetchAvailableForms() {
  return axios.get<FormLite[]>("/workflow-config/forms");
}
export async function fetchWorkflowUsers() {
  return axios.get<UserLite[]>("/workflow-config/users");
}
export async function fetchWorkflowById(id: number) {
  return axios.get<ExportedWorkflow>(`/workflows/${id}`);
}
export async function fetchFormFields(formId: number) {
  return axios.get<FormFieldLite[]>(`/workflow-config/forms/${formId}/fields`);
}
export const archiveWorkflow = (id: number) => axios.patch(`/workflows/${id}/archive`);
export const enableWorkflow = (id: number) => axios.patch(`/workflows/${id}/enable`);
export const draftWorkflow = (id: number) => axios.patch(`/workflows/${id}/draft`);
export const publishWorkflow = (id: number) => axios.patch(`/workflows/${id}/publish`);

export const api = {
  createWorkflow,
  updateWorkflow,
  archiveWorkflow,
  enableWorkflow,
  draftWorkflow,
  publishWorkflow,
  fetchAvailableForms,
  fetchWorkflowUsers,
  fetchWorkflowById,
  fetchFormFields,
};