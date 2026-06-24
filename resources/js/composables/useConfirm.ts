import { reactive } from 'vue';

export type ConfirmVariant = 'default' | 'destructive';

export type ConfirmOptions = {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: ConfirmVariant;
};

type ConfirmState = {
    open: boolean;
    title: string;
    description: string | undefined;
    confirmText: string;
    cancelText: string;
    variant: ConfirmVariant;
    resolve: ((v: boolean) => void) | null;
};

export const confirmState = reactive<ConfirmState>({
    open: false,
    title: '',
    description: undefined,
    confirmText: 'Tasdiqlash',
    cancelText: 'Bekor qilish',
    variant: 'default',
    resolve: null,
});

export function confirm(options: ConfirmOptions | string): Promise<boolean> {
    const opts: ConfirmOptions = typeof options === 'string' ? { title: options } : options;

    if (confirmState.resolve) {
        confirmState.resolve(false);
    }

    return new Promise<boolean>((resolve) => {
        confirmState.title = opts.title;
        confirmState.description = opts.description;
        confirmState.confirmText = opts.confirmText ?? 'Tasdiqlash';
        confirmState.cancelText = opts.cancelText ?? 'Bekor qilish';
        confirmState.variant = opts.variant ?? 'default';
        confirmState.resolve = resolve;
        confirmState.open = true;
    });
}

export function respondConfirm(value: boolean): void {
    const r = confirmState.resolve;
    confirmState.resolve = null;
    confirmState.open = false;
    r?.(value);
}

export function useConfirm() {
    return confirm;
}
