export interface PageProps {
  auth?: {
    user: {
      id: number;
      name: string;
      email: string;
      account_id: number;
      [key: string]: unknown;
    };
  };
  flash?: {
    success?: string;
    error?: string;
  };
  errors?: Record<string, string>;
  [key: string]: unknown;
}
