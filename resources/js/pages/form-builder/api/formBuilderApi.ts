import axios from "axios";
import { FormBuilderState } from "../types/formBuilderTypes";

// Save the form and fields in one call (adjust endpoint as per your backend)
export async function saveFormApi(form: FormBuilderState) {
  return axios.post("/forms", form);
}

export async function updateFormApi(formId: number, form: FormBuilderState) {
  return axios.put(`/forms/${formId}`, form, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });
}

// List only the 3 form-access permissions
export async function listFormPermissionsApi() {
  return axios.get("/admin/forms/permissions");
}

export async function listFormCategoriesApi() {
  return await axios.get("/admin/forms/categories");
}

