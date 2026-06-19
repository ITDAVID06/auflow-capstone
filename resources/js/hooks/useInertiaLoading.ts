import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";

export function useInertiaLoading(): boolean {
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    let activeVisits = 0;

    const removeStart = router.on("start", () => {
      activeVisits += 1;
      setIsLoading(true);
    });

    const removeFinish = router.on("finish", () => {
      activeVisits = Math.max(0, activeVisits - 1);
      setIsLoading(activeVisits > 0);
    });

    const clearLoading = () => {
      activeVisits = 0;
      setIsLoading(false);
    };

    const removeError = router.on("error", clearLoading);
    const removeInvalid = router.on("invalid", clearLoading);
    const removeException = router.on("exception", () => {
      clearLoading();
      return false;
    });

    return () => {
      removeStart();
      removeFinish();
      removeError();
      removeInvalid();
      removeException();
    };
  }, []);

  return isLoading;
}
