import { useRef, useState } from 'react';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { UserPen, X } from 'lucide-react';
import UserForm from './UserForm';
import type { User, Role, UserStatusOption } from '../types';

interface EditUserDialogProps {
  user: User | null;
  roles: Role[];
  statuses: UserStatusOption[];
  open: boolean;
  setOpen: (open: boolean) => void;
  onSubmitted?: () => void;
}

export default function EditUserDialog({ user, roles, statuses, open, setOpen, onSubmitted }: EditUserDialogProps) {
  const dirtyRef = useRef(false);
  const [confirmClose, setConfirmClose] = useState(false);

  const handleOpenChange = (v: boolean) => {
    if (!v && dirtyRef.current) {
      setConfirmClose(true);
    } else {
      setOpen(v);
    }
  };

  const doClose = () => {
    dirtyRef.current = false;
    setConfirmClose(false);
    setOpen(false);
  };

  return (
    <>
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent hideClose className="w-[95vw] max-w-[95vw] lg:max-w-[960px] max-h-[92vh] p-0 flex flex-col overflow-hidden rounded-2xl">
          <DialogHeader className="px-6 pt-5 pb-4 md:px-8 md:pt-6 md:pb-5 shrink-0 border-b border-border/70 bg-muted/20">
            <div className="flex items-start justify-between gap-2">
              <div>
                <DialogTitle className="text-xl flex items-center gap-2">
                  <UserPen className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                  Edit User
                </DialogTitle>
                <DialogDescription className="mt-1">
                  Update account details and assigned roles.
                </DialogDescription>
              </div>
              <DialogClose
                onClick={() => handleOpenChange(false)}
                className="mt-0.5 rounded-md p-1 opacity-70 transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring"
                aria-label="Close"
              >
                <X className="h-5 w-5" />
              </DialogClose>
            </div>
          </DialogHeader>

          <div className="px-6 py-5 md:px-8 md:py-6 overflow-y-auto flex-1 bg-background">
            <UserForm
              key={user?.account_id ?? 'edit'}
              user={user}
              roles={roles}
              statuses={statuses}
              method="put"
              onDirtyChange={(dirty) => { dirtyRef.current = dirty; }}
              onSubmit={() => {
                dirtyRef.current = false;
                setOpen(false);
                onSubmitted?.();
              }}
              onClose={doClose}
            />
          </div>
        </DialogContent>
      </Dialog>

      <AlertDialog open={confirmClose} onOpenChange={setConfirmClose}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Unsaved changes</AlertDialogTitle>
            <AlertDialogDescription>
              You have unsaved changes. Are you sure you want to close without saving?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Stay</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={doClose}
            >
              Discard &amp; close
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}