<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/date';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';

const { t, locale } = useI18n();
const { symbol } = useCurrency();
const unitLabel = useUnitLabel();

type Item = {
    id: number;
    product_name: string;
    display_name?: string | null;
    price: number;
    pack_price?: number | null;
    qty: number;
    pack_qty?: number | null;
    pack_size?: number | null;
    delivered_qty?: number | null;
    delivered_pack_qty?: number | null;
    unit?: string | null;
    subtotal: number;
    delivered_subtotal?: number | null;
};

type OrderPayload = {
    data: {
        id: number;
        number: number;
        status: string;
        status_label: string;
        total: number;
        paid_amount: number | null;
        delivered_total: number | null;
        balance_before: number | null;
        balance_after: number | null;
        delivered_at: string | null;
        received_at: string | null;
        note: string | null;
        created_at: string;
        items: Item[];
        shop?: {
            id: number;
            name: string;
            phone: string | null;
            address: string | null;
            inn?: string | null;
            balance: number;
        } | null;
        dealer?: { id: number; name: string } | null;
        deliveryman?: { id: number; name: string; phone: string | null } | null;
    };
};

const props = defineProps<{ order: OrderPayload }>();

const o = props.order.data;

const hasBalanceSnapshot = computed<boolean>(
    () =>
        (o.delivered_at !== null || o.received_at !== null) &&
        o.balance_before !== null &&
        o.balance_after !== null,
);

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function balanceClass(n: number): string {
    if (n < 0) {
        return 'text-amber-700';
    }

    if (n > 0) {
        return 'text-emerald-700';
    }

    return '';
}

function formatNum(n: number): string {
    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
}

function itemQtyLabel(it: Item): string {
    const totalQty = it.delivered_qty ?? it.qty;
    const packSize = it.pack_size ?? 0;
    const packQty = it.delivered_pack_qty ?? it.pack_qty ?? 0;
    const unit = unitLabel(it.unit);
    const blok = t('pageDealer.assembleModal.blok');

    if (packQty > 0 && packSize > 1) {
        const loose = totalQty - packQty * packSize;

        if (loose > 0) {
            return `${formatNum(packQty)} ${blok} + ${formatNum(loose)} ${unit}`;
        }

        return `${formatNum(packQty)} ${blok} (${formatNum(totalQty)} ${unit})`;
    }

    return `${formatNum(totalQty)} ${unit}`.trim();
}

function itemSubtotal(it: Item): number {
    return (
        it.delivered_subtotal ??
        it.subtotal ??
        (it.delivered_qty ?? it.qty) * it.price
    );
}

function printA4(): void {
    window.print();
}

function escHtml(s: unknown): string {
    return String(s ?? '').replace(/[&<>"']/g, (c) => {
        const map: Record<string, string> = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        };

        return map[c]!;
    });
}

function buildChekHtml(): string {
    const soum = symbol.value;

    const stackRow = (
        label: string,
        value: string,
        opts: { bold?: boolean; rule?: boolean } = {},
    ): string => {
        const valCls = ['value', opts.bold ? 'strong' : '']
            .filter(Boolean)
            .join(' ');
        const rowCls = ['stack', opts.rule ? 'rule-top' : '']
            .filter(Boolean)
            .join(' ');

        return `<div class="${rowCls}"><div class="label">${escHtml(label)}</div><div class="${valCls}">${escHtml(value)}</div></div>`;
    };

    let parties = `<div class="stack"><div class="strong">${escHtml(o.shop?.name ?? '—')}</div>`;

    if (o.shop?.phone) {
        parties += `<div>${escHtml(o.shop.phone)}</div>`;
    }

    if (o.shop?.address) {
        parties += `<div>${escHtml(o.shop.address)}</div>`;
    }

    if (o.shop?.inn) {
        parties += `<div>INN: ${escHtml(o.shop.inn)}</div>`;
    }

    parties += `</div>`;
    parties += stackRow(t('pageDealer.ordersInvoice.status'), o.status_label);

    if (o.deliveryman) {
        parties += `<div class="stack"><div class="label">${escHtml(t('pageDealer.ordersInvoice.deliveryman'))}:</div><div class="strong">${escHtml(o.deliveryman.name)}</div>`;

        if (o.deliveryman.phone) {
            parties += `<div>${escHtml(o.deliveryman.phone)}</div>`;
        }

        parties += `</div>`;
    }

    if (o.delivered_at) {
        parties += stackRow(
            t('pageDealer.ordersInvoice.delivered'),
            formatDateTime(o.delivered_at),
        );
    }

    if (o.received_at) {
        parties += stackRow(
            t('pageDealer.ordersInvoice.received'),
            formatDateTime(o.received_at),
        );
    }

    const items = o.items
        .map((it, i) => {
            const name = escHtml(it.display_name ?? it.product_name);
            const qty = escHtml(itemQtyLabel(it));
            const price = formatMoney(it.price);
            const sum = formatMoney(itemSubtotal(it));

            return `<div class="item"><div class="strong">${i + 1}. ${name}</div><div>${price} × ${qty}</div><div class="value strong">${sum} ${escHtml(soum)}</div></div>`;
        })
        .join('<div class="item-sep"></div>');

    let totals = stackRow(
        t('pageDealer.ordersInvoice.totalOrder'),
        `${formatMoney(o.total)} ${soum}`,
    );

    if (o.delivered_total) {
        totals += stackRow(
            t('pageDealer.ordersInvoice.deliveredAmount'),
            `${formatMoney(o.delivered_total)} ${soum}`,
        );
    }

    if (o.paid_amount) {
        totals += stackRow(
            t('pageDealer.ordersInvoice.paid'),
            `${formatMoney(o.paid_amount)} ${soum}`,
        );
    }

    if (hasBalanceSnapshot.value) {
        totals += stackRow(
            t('pageDealer.ordersInvoice.balanceBefore'),
            `${formatMoney(o.balance_before!)} ${soum}`,
        );
        totals += stackRow(
            t('pageDealer.ordersInvoice.balanceAfter'),
            `${formatMoney(o.balance_after!)} ${soum}`,
            { bold: true, rule: true },
        );
    } else if (o.shop) {
        totals += stackRow(
            t('pageDealer.ordersInvoice.shopBalance'),
            `${formatMoney(o.shop.balance)} ${soum}`,
            { bold: true, rule: true },
        );
    }

    const note = o.note ? `<div class="note">"${escHtml(o.note)}"</div>` : '';

    return (
        `<!DOCTYPE html>
<html lang="${escHtml(locale.value)}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${escHtml(t('pageDealer.ordersInvoice.receiptTitle'))} #${o.number}</title>
<style>
@page { size: auto; margin: 0; }
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    -webkit-font-smoothing: none;
    -moz-osx-font-smoothing: grayscale;
    font-smooth: never;
    text-rendering: geometricPrecision;
}
html, body { background: #fff; color: #000; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.4;
    letter-spacing: 0.15px;
    word-break: break-word;
    overflow-wrap: break-word;
    color: #000;
}
#receipt {
    padding: 4px;
    width: 58mm;
    max-width: 58mm;
    margin: 0 auto;
}
#print-btn, #back-btn {
    position: fixed;
    top: 8px;
    z-index: 9999;
    padding: 10px 16px;
    color: #fff;
    border: 0;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    cursor: pointer;
}
#print-btn {
    right: 8px;
    background: #2563eb;
}
#back-btn {
    left: 8px;
    background: #6b7280;
}
.center { text-align: center; }
.name { font-size: 14px; font-weight: 700; }
.id { font-size: 13px; font-weight: 700; margin-top: 2px; }
.date { font-size: 10px; margin-top: 1px; }
.sep { border-top: 1px dashed #000; margin: 5px 0; }
.strong { font-weight: 700; }
.label { font-weight: 400; }
.value { text-align: right; }
.stack { margin-bottom: 4px; }
.stack.rule-top { border-top: 1px solid #000; padding-top: 4px; margin-top: 5px; }
.item { margin-bottom: 5px; }
.item-sep { border-top: 1px dashed #000; margin: 0 0 5px 0; }
.note { margin-top: 6px; font-style: italic; }
.footer { margin-top: 8px; text-align: center; font-weight: 700; font-size: 12px; }
@media print {
    #print-btn, #back-btn { display: none !important; }
    #receipt { margin: 0; }
}
</style>
</head>
<body>
<button id="back-btn" type="button" onclick="window.close()">← ${escHtml(t('pageDealer.ordersInvoice.receiptBack'))}</button>
<button id="print-btn" type="button" onclick="window.print()">${escHtml(t('pageDealer.ordersInvoice.printShort'))}</button>
<div id="receipt">
<div class="center">
    <div class="name">${escHtml(o.dealer?.name ?? '—')}</div>
    <div class="id">#${o.number}</div>
    <div class="date">${escHtml(formatDateTime(o.created_at))}</div>
</div>
<div class="sep"></div>
${parties}
<div class="sep"></div>
${items}
<div class="sep"></div>
${totals}
${note}
<div class="sep"></div>
<div class="footer">${escHtml(t('pageDealer.ordersInvoice.receiptThanks'))}</div>
</div>
<scr` +
        `ipt>
window.addEventListener('load', function() {
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            setTimeout(function() {
                try { window.print(); } catch (e) {}
            }, 500);
        });
    });
});
</scr` +
        `ipt>
</body>
</html>`
    );
}

/**
 * 58mm chek print — Blob URL orqali yangi tab'da ochiladi.
 *
 * Android Chrome'da `document.write` + popup window kombinatsiyasi unstable:
 * preview rasterga yetib bormaydi, xprinter app bo'sh PDF oladi. Blob URL
 * yondashuvi real document navigation sifatida ishlaydi va mobil browser'lar
 * uni to'liq render qiladi. Auto-print fail bo'lsa, oynada "Chop etish"
 * tugmasi mavjud.
 */
function printCheck58(): void {
    const html = buildChekHtml();
    const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const w = window.open(url, '_blank');

    if (!w) {
        URL.revokeObjectURL(url);
        alert(t('pageDealer.ordersInvoice.popupBlocked'));

        return;
    }

    setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
</script>

<template>
    <Head
        :title="t('pageDealer.ordersInvoice.headTitle', { number: o.number })"
    />

    <!-- Screen-only toolbar — print'da yashiriladi -->
    <div class="invoice-toolbar mx-auto w-full max-w-2xl p-4 sm:p-6">
        <div class="flex items-center justify-between">
            <Button
                variant="ghost"
                size="icon"
                @click="router.get(`/dealer/orders/${o.id}`)"
            >
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button>
                        <Printer class="mr-2 h-4 w-4" />
                        <span class="hidden sm:inline">{{
                            t('pageDealer.ordersInvoice.print')
                        }}</span>
                        <span class="sm:hidden">{{
                            t('pageDealer.ordersInvoice.printShort')
                        }}</span>
                        <ChevronDown class="ml-1 h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem @click="printCheck58">
                        🧾 {{ t('pageDealer.ordersInvoice.printCheck58') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="printA4">
                        🖨️ {{ t('pageDealer.ordersInvoice.printA4') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>

    <!-- Faqat shu qism print'ga chiqadi -->
    <section
        class="invoice-print mx-auto w-full max-w-2xl rounded-lg border bg-white p-5 text-black shadow-sm sm:p-6"
    >
        <!-- Header -->
        <div class="invoice-header flex items-baseline justify-between gap-3">
            <h2 class="text-lg leading-tight font-semibold">
                {{ o.dealer?.name ?? '—' }}
            </h2>
            <div class="text-right">
                <p class="invoice-id font-mono text-base font-semibold">
                    #{{ o.number }}
                </p>
                <p class="text-[11px] text-neutral-500">
                    {{ formatDateTime(o.created_at) }}
                </p>
            </div>
        </div>

        <hr class="my-3 border-neutral-300" />

        <!-- Parties: compact rows -->
        <div class="invoice-parties space-y-1 text-sm">
            <div class="flex justify-between gap-3">
                <span class="text-neutral-500"
                    >{{ t('pageDealer.ordersInvoice.shop') }}:</span
                >
                <span class="text-right font-medium break-words">{{
                    o.shop?.name ?? '—'
                }}</span>
            </div>
            <div v-if="o.shop?.phone" class="flex justify-between gap-3">
                <span class="text-neutral-500">{{ o.shop.phone }}</span>
                <span
                    v-if="o.shop?.inn"
                    class="font-mono text-xs text-neutral-500"
                    >INN: {{ o.shop.inn }}</span
                >
            </div>
            <div
                v-if="o.shop?.address"
                class="text-xs break-words text-neutral-600"
            >
                {{ o.shop.address }}
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-neutral-500"
                    >{{ t('pageDealer.ordersInvoice.status') }}:</span
                >
                <span class="font-medium">{{ o.status_label }}</span>
            </div>
            <div v-if="o.deliveryman" class="flex justify-between gap-3">
                <span class="text-neutral-500"
                    >{{ t('pageDealer.ordersInvoice.deliveryman') }}:</span
                >
                <span class="text-right"
                    >{{ o.deliveryman.name
                    }}<span v-if="o.deliveryman.phone" class="text-neutral-500">
                        · {{ o.deliveryman.phone }}</span
                    ></span
                >
            </div>
            <div
                v-if="o.delivered_at"
                class="flex justify-between gap-3 text-xs text-neutral-500"
            >
                <span>{{ t('pageDealer.ordersInvoice.delivered') }}:</span>
                <span>{{ formatDateTime(o.delivered_at) }}</span>
            </div>
            <div
                v-if="o.received_at"
                class="flex justify-between gap-3 text-xs text-neutral-500"
            >
                <span>{{ t('pageDealer.ordersInvoice.received') }}:</span>
                <span>{{ formatDateTime(o.received_at) }}</span>
            </div>
        </div>

        <table class="invoice-items mt-4 w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-400 text-left">
                    <th class="py-1 pr-2 font-medium">#</th>
                    <th class="py-1 pr-2 font-medium">
                        {{ t('pageDealer.ordersInvoice.tableProduct') }}
                    </th>
                    <th class="py-1 pr-2 text-right font-medium">
                        {{ t('pageDealer.ordersInvoice.tablePrice') }}
                    </th>
                    <th class="py-1 pr-2 text-right font-medium">
                        {{ t('pageDealer.ordersInvoice.tableQty') }}
                    </th>
                    <th class="py-1 text-right font-medium">
                        {{ t('pageDealer.ordersInvoice.tableSum') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(it, i) in o.items"
                    :key="it.id"
                    class="border-b border-neutral-200 align-top"
                >
                    <td class="py-1 pr-2 text-neutral-500">{{ i + 1 }}</td>
                    <td class="py-1 pr-2 break-words">
                        {{ it.display_name ?? it.product_name }}
                    </td>
                    <td
                        class="py-1 pr-2 text-right font-mono whitespace-nowrap"
                    >
                        {{ formatMoney(it.price) }}
                    </td>
                    <td
                        class="py-1 pr-2 text-right font-mono whitespace-nowrap"
                    >
                        {{ itemQtyLabel(it) }}
                    </td>
                    <td class="py-1 text-right font-mono whitespace-nowrap">
                        {{ formatMoney(itemSubtotal(it)) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="invoice-totals mt-3 space-y-0.5 text-sm">
            <div class="flex justify-between gap-3">
                <span class="text-neutral-500">{{
                    t('pageDealer.ordersInvoice.totalOrder')
                }}</span>
                <span class="font-mono"
                    >{{ formatMoney(o.total) }} {{ symbol }}</span
                >
            </div>
            <div v-if="o.delivered_total" class="flex justify-between gap-3">
                <span class="text-neutral-500">{{
                    t('pageDealer.ordersInvoice.deliveredAmount')
                }}</span>
                <span class="font-mono"
                    >{{ formatMoney(o.delivered_total) }} {{ symbol }}</span
                >
            </div>
            <div v-if="o.paid_amount" class="flex justify-between gap-3">
                <span class="text-neutral-500">{{
                    t('pageDealer.ordersInvoice.paid')
                }}</span>
                <span class="font-mono"
                    >{{ formatMoney(o.paid_amount) }} {{ symbol }}</span
                >
            </div>

            <template v-if="hasBalanceSnapshot">
                <div
                    class="flex justify-between gap-3 border-t border-neutral-200 pt-1"
                >
                    <span class="text-neutral-500">{{
                        t('pageDealer.ordersInvoice.balanceBefore')
                    }}</span>
                    <span
                        class="font-mono"
                        :class="balanceClass(o.balance_before!)"
                    >
                        {{ formatMoney(o.balance_before!) }} {{ symbol }}
                    </span>
                </div>
                <div
                    class="flex justify-between gap-3 border-t border-neutral-700 pt-1 font-semibold"
                >
                    <span>{{
                        t('pageDealer.ordersInvoice.balanceAfter')
                    }}</span>
                    <span
                        class="font-mono"
                        :class="balanceClass(o.balance_after!)"
                    >
                        {{ formatMoney(o.balance_after!) }} {{ symbol }}
                    </span>
                </div>
            </template>

            <div
                v-else-if="o.shop"
                class="flex justify-between gap-3 border-t border-neutral-700 pt-1 font-semibold"
            >
                <span>{{ t('pageDealer.ordersInvoice.shopBalance') }}</span>
                <span class="font-mono" :class="balanceClass(o.shop.balance)">
                    {{ formatMoney(o.shop.balance) }} {{ symbol }}
                </span>
            </div>
        </div>

        <p
            v-if="o.note"
            class="mt-3 border-t border-neutral-200 pt-2 text-sm break-words italic"
        >
            "{{ o.note }}"
        </p>

        <div class="invoice-signatures mt-8 grid grid-cols-2 gap-8 text-sm">
            <div>
                <p class="text-neutral-500">
                    {{ t('pageDealer.ordersInvoice.givenBy') }}
                </p>
                <p v-if="o.deliveryman" class="mt-1">
                    {{ o.deliveryman.name }}
                </p>
                <p v-else class="mt-1 text-neutral-400">—</p>
                <div
                    class="mt-6 border-t border-neutral-400 pt-1 text-xs text-neutral-500"
                >
                    {{ t('pageDealer.ordersInvoice.signatureDate') }}
                </div>
            </div>
            <div>
                <p class="text-neutral-500">
                    {{ t('pageDealer.ordersInvoice.receivedBy') }}
                </p>
                <div
                    class="mt-7 border-t border-neutral-400 pt-1 text-xs text-neutral-500"
                >
                    {{ t('pageDealer.ordersInvoice.signatureDate') }}
                </div>
            </div>
        </div>
    </section>
</template>

<style>
@media print {
    @page {
        size: auto;
        margin: 10mm;
    }

    html,
    body {
        background: #fff !important;
        color: #000 !important;
    }

    body * {
        visibility: hidden !important;
    }

    .invoice-print,
    .invoice-print * {
        visibility: visible !important;
    }

    .invoice-print {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: none !important;
        margin: 0 !important;
        padding: 4mm !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
        font-size: 12px !important;
    }

    .invoice-toolbar {
        display: none !important;
    }
}
</style>
