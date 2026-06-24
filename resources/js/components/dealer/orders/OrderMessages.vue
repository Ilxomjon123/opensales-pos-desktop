<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, ChevronDown, MessageSquare, Pencil, Send, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';
import { formatDateTime } from '@/lib/date';
import type { OrderMessageEntry } from '@/types';

const props = defineProps<{
    orderId: number;
    messages: OrderMessageEntry[];
}>();

const { t } = useI18n();

const list = computed(() => props.messages ?? []);

const open = ref(true);

const body = ref('');
const sending = ref(false);

const editingId = ref<number | null>(null);
const editBody = ref('');

const composeEl = ref<HTMLTextAreaElement | null>(null);

// Textarea matnga qarab o'sadi (max-h-40 gacha, keyin scroll)
function autoGrow(e: Event) {
    grow(e.target as HTMLTextAreaElement);
}

function grow(el: HTMLTextAreaElement | null) {
    if (!el) {
        return;
    }

    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
}

function send() {
    if (!body.value.trim()) {
        return;
    }

    sending.value = true;

    router.post(`/dealer/orders/${props.orderId}/messages`, { body: body.value }, {
        preserveScroll: true,
        onSuccess: () => {
 body.value = ''; grow(composeEl.value); 
},
        onFinish: () => {
 sending.value = false; 
},
    });
}

function startEdit(m: OrderMessageEntry) {
    editingId.value = m.id;
    editBody.value = m.body;
}

function cancelEdit() {
    editingId.value = null;
    editBody.value = '';
}

function saveEdit(m: OrderMessageEntry) {
    if (!editBody.value.trim()) {
        return;
    }

    router.put(`/dealer/orders/${props.orderId}/messages/${m.id}`, { body: editBody.value }, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

async function remove(m: OrderMessageEntry) {
    const ok = await confirm({
        title: t('pageDealer.orders.deleteMessageTitle'),
        description: t('pageDealer.orders.deleteMessageDesc'),
        confirmText: t('pageDealer.orders.delete'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    router.delete(`/dealer/orders/${props.orderId}/messages/${m.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Card :class="open ? 'gap-3 py-3' : 'gap-0 py-0'">
        <CardHeader class="px-3 sm:px-4">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 py-2 text-left"
                :aria-expanded="open"
                @click="open = !open"
            >
                <CardTitle class="flex items-center gap-2 text-sm">
                    <MessageSquare class="h-4 w-4 text-muted-foreground" />
                    {{ t('pageDealer.orders.messages') }}
                    <span v-if="list.length" class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] font-mono leading-none">{{ list.length }}</span>
                </CardTitle>
                <ChevronDown
                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                    :class="open ? 'rotate-180' : ''"
                />
            </button>
        </CardHeader>

        <CardContent v-show="open" class="flex flex-col gap-2 px-3 sm:px-4">
            <!-- Thread -->
            <div
                v-for="m in list"
                :key="m.id"
                class="group rounded-lg bg-muted/30 px-2.5 py-2"
            >
                <template v-if="editingId === m.id">
                    <textarea
                        v-model="editBody"
                        rows="2"
                        maxlength="4000"
                        class="max-h-40 w-full resize-none overflow-y-auto rounded-md border border-input bg-background px-2.5 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        @input="autoGrow"
                    />
                    <div class="mt-1.5 flex justify-end gap-1.5">
                        <Button variant="ghost" size="sm" class="h-7 px-2" @click="cancelEdit">
                            <X class="h-3.5 w-3.5" />
                        </Button>
                        <Button size="sm" class="h-7 px-2.5" :disabled="!editBody.trim()" @click="saveEdit(m)">
                            <Check class="mr-1 h-3.5 w-3.5" /> {{ t('pageDealer.orders.save') }}
                        </Button>
                    </div>
                </template>
                <template v-else>
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 flex-1 whitespace-pre-wrap break-words text-sm leading-snug">{{ m.body }}</p>
                        <div class="-mr-1 flex shrink-0 items-center gap-0.5 opacity-70 transition-opacity group-hover:opacity-100">
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                :title="t('pageDealer.orders.edit')"
                                @click="startEdit(m)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded-md text-rose-500 hover:bg-rose-500/10"
                                :title="t('pageDealer.orders.delete')"
                                @click="remove(m)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                    <p class="mt-0.5 text-[10px] text-muted-foreground">
                        <span v-if="m.author">{{ m.author.name }} · </span>
                        <span>{{ m.created_at ? formatDateTime(m.created_at) : '' }}</span>
                        <span v-if="m.edited" class="italic"> · {{ t('pageDealer.orders.editedSuffix') }}</span>
                    </p>
                </template>
            </div>

            <p v-if="!list.length" class="py-1 text-xs text-muted-foreground">{{ t('pageDealer.orders.noMessagesYet') }}</p>

            <!-- Compose -->
            <div class="flex items-end gap-2">
                <textarea
                    ref="composeEl"
                    v-model="body"
                    rows="1"
                    maxlength="4000"
                    :placeholder="t('pageDealer.orders.writeToShop')"
                    class="max-h-40 min-h-9 flex-1 resize-none overflow-y-auto rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @input="autoGrow"
                />
                <Button size="sm" class="h-9 shrink-0" :disabled="sending || !body.trim()" @click="send">
                    <Send class="h-3.5 w-3.5 sm:mr-1.5" />
                    <span class="hidden sm:inline">{{ t('pageDealer.orders.send') }}</span>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
