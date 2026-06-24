<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { confirmState, respondConfirm } from '@/composables/useConfirm';

const open = computed({
    get: () => confirmState.open,
    set: (v: boolean) => {
        if (!v) {
respondConfirm(false);
}
    },
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div
                        v-if="confirmState.variant === 'destructive'"
                        class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                    >
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                    <div class="flex-1 space-y-1.5">
                        <DialogTitle>{{ confirmState.title }}</DialogTitle>
                        <DialogDescription v-if="confirmState.description">
                            {{ confirmState.description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="respondConfirm(false)">
                    {{ confirmState.cancelText }}
                </Button>
                <Button
                    :variant="confirmState.variant === 'destructive' ? 'destructive' : 'default'"
                    @click="respondConfirm(true)"
                >
                    {{ confirmState.confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
