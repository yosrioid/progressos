import { reactive } from 'vue';

type ToastTone = 'success' | 'error' | 'info';

type Toast = {
  id: number;
  tone: ToastTone;
  title: string;
  message?: string;
};

type ConfirmState = {
  open: boolean;
  title: string;
  message: string;
  confirmLabel: string;
  danger: boolean;
  resolve?: (confirmed: boolean) => void;
};

export const feedback = reactive({
  toasts: [] as Toast[],
  confirm: {
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    danger: false,
    resolve: undefined,
  } as ConfirmState,
});

let nextToastId = 1;

export function toast(toast: Omit<Toast, 'id'>) {
  const id = nextToastId++;
  feedback.toasts.push({ id, ...toast });
  window.setTimeout(() => dismissToast(id), 4200);
}

export function dismissToast(id: number) {
  const index = feedback.toasts.findIndex((item) => item.id === id);
  if (index >= 0) feedback.toasts.splice(index, 1);
}

export function confirmAction(options: { title: string; message: string; confirmLabel?: string; danger?: boolean }) {
  return new Promise<boolean>((resolve) => {
    feedback.confirm.open = true;
    feedback.confirm.title = options.title;
    feedback.confirm.message = options.message;
    feedback.confirm.confirmLabel = options.confirmLabel || 'Confirm';
    feedback.confirm.danger = Boolean(options.danger);
    feedback.confirm.resolve = resolve;
  });
}

export function resolveConfirm(confirmed: boolean) {
  feedback.confirm.resolve?.(confirmed);
  feedback.confirm.open = false;
  feedback.confirm.resolve = undefined;
}
