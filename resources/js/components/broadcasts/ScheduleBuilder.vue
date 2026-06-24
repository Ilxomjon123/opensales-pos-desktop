<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus, X } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

type ScheduleType = 'once' | 'daily' | 'weekly' | 'monthly';

type ScheduleConfig = {
    datetime?: string;
    time?: string;
    times?: string[];
    days?: number[];
};

type Option = { value: string; label: string };

const props = defineProps<{
    modelType: ScheduleType;
    modelConfig: ScheduleConfig;
    timezone: string;
    startsAt: string | null;
    endsAt: string | null;
    options: Option[];
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:modelType': [v: ScheduleType];
    'update:modelConfig': [v: ScheduleConfig];
    'update:timezone': [v: string];
    'update:startsAt': [v: string | null];
    'update:endsAt': [v: string | null];
}>();

const weekDays = computed(() => [
    { value: 1, label: t('component.broadcast.schedule.mon') },
    { value: 2, label: t('component.broadcast.schedule.tue') },
    { value: 3, label: t('component.broadcast.schedule.wed') },
    { value: 4, label: t('component.broadcast.schedule.thu') },
    { value: 5, label: t('component.broadcast.schedule.fri') },
    { value: 6, label: t('component.broadcast.schedule.sat') },
    { value: 0, label: t('component.broadcast.schedule.sun') },
]);

const monthDays = Array.from({ length: 31 }, (_, i) => i + 1);

// Bir kunda bir necha yuborish vaqti. Eski yagona `time` ham qo'llab-quvvatlanadi.
const times = computed<string[]>(() => {
    const t = props.modelConfig.times;

    if (Array.isArray(t) && t.length > 0) {
        return t as string[];
    }

    return [props.modelConfig.time ?? '09:00'];
});

function setTimes(arr: string[]) {
    emit('update:modelConfig', { ...props.modelConfig, times: arr });
}

function updateTime(index: number, v: string) {
    const arr = [...times.value];
    arr[index] = v;
    setTimes(arr);
}

function addTime() {
    setTimes([...times.value, '12:00']);
}

function removeTime(index: number) {
    const arr = times.value.filter((_, i) => i !== index);
    setTimes(arr.length > 0 ? arr : ['09:00']);
}

const datetime = computed({
    get: () => props.modelConfig.datetime ?? '',
    set: (v: string) =>
        emit('update:modelConfig', { ...props.modelConfig, datetime: v }),
});

const days = computed<number[]>(() =>
    Array.isArray(props.modelConfig.days) ? props.modelConfig.days : [],
);

function toggleDay(d: number) {
    const set = new Set(days.value);

    if (set.has(d)) {
        set.delete(d);
    } else {
        set.add(d);
    }

    emit('update:modelConfig', {
        ...props.modelConfig,
        days: Array.from(set).sort((a, b) => a - b),
    });
}

function setType(t: ScheduleType) {
    emit('update:modelType', t);

    if (t === 'once') {
        emit('update:modelConfig', { datetime: datetime.value || '' });
    } else if (t === 'daily') {
        emit('update:modelConfig', { times: times.value });
    } else {
        emit('update:modelConfig', { times: times.value, days: days.value });
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">{{
                t('component.broadcast.schedule.title')
            }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Type tabs -->
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="opt in options"
                    :key="opt.value"
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm transition-colors"
                    :class="
                        modelType === opt.value
                            ? 'border-primary bg-primary/10 font-medium text-primary'
                            : 'border-input hover:border-primary/40'
                    "
                    @click="setType(opt.value as ScheduleType)"
                >
                    {{ opt.label }}
                </button>
            </div>

            <!-- Once: datetime picker -->
            <div v-if="modelType === 'once'" class="space-y-2">
                <Label>{{ t('component.broadcast.schedule.dateTime') }}</Label>
                <input
                    type="datetime-local"
                    v-model="datetime"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <p
                    v-if="errors?.['schedule_config.datetime']"
                    class="text-sm text-red-600"
                >
                    {{ errors['schedule_config.datetime'] }}
                </p>
            </div>

            <!-- Weekly: day-of-week selector -->
            <div v-if="modelType === 'weekly'">
                <Label class="mb-2 block">{{
                    t('component.broadcast.schedule.weekDays')
                }}</Label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="d in weekDays"
                        :key="d.value"
                        type="button"
                        class="h-9 w-12 rounded-md border text-sm transition-colors"
                        :class="
                            days.includes(d.value)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input hover:border-primary/40'
                        "
                        @click="toggleDay(d.value)"
                    >
                        {{ d.label }}
                    </button>
                </div>
            </div>

            <!-- Monthly: day-of-month grid -->
            <div v-if="modelType === 'monthly'">
                <Label class="mb-2 block">{{
                    t('component.broadcast.schedule.monthDays')
                }}</Label>
                <div class="grid grid-cols-7 gap-1.5 sm:grid-cols-10">
                    <button
                        v-for="d in monthDays"
                        :key="d"
                        type="button"
                        class="h-9 rounded-md border text-sm transition-colors"
                        :class="
                            days.includes(d)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input hover:border-primary/40'
                        "
                        @click="toggleDay(d)"
                    >
                        {{ d }}
                    </button>
                </div>
            </div>

            <!-- Yuborish vaqtlari: bir kunda bir necha marta -->
            <div v-if="modelType !== 'once'" class="space-y-2">
                <Label>{{
                    modelType === 'daily'
                        ? t('component.broadcast.schedule.dailyTimes')
                        : t('component.broadcast.schedule.sendTimes')
                }}</Label>
                <div class="space-y-2">
                    <div
                        v-for="(time, i) in times"
                        :key="i"
                        class="flex items-center gap-2"
                    >
                        <input
                            type="time"
                            :value="time"
                            @input="
                                updateTime(
                                    i,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                            class="w-full max-w-[180px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <button
                            v-if="times.length > 1"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-md border border-input text-muted-foreground transition-colors hover:border-red-400 hover:text-red-500"
                            :title="t('component.broadcast.schedule.delete')"
                            @click="removeTime(i)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-dashed border-input px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:text-primary"
                    @click="addTime"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('component.broadcast.schedule.addTime') }}
                </button>
            </div>

            <!-- Optional window -->
            <div class="grid grid-cols-1 gap-3 border-t pt-4 sm:grid-cols-2">
                <div>
                    <Label class="mb-1.5 block">{{
                        t('component.broadcast.schedule.startDate')
                    }}</Label>
                    <input
                        type="datetime-local"
                        :value="startsAt ?? ''"
                        @input="
                            emit(
                                'update:startsAt',
                                ($event.target as HTMLInputElement).value ||
                                    null,
                            )
                        "
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <Label class="mb-1.5 block">{{
                        t('component.broadcast.schedule.endDate')
                    }}</Label>
                    <input
                        type="datetime-local"
                        :value="endsAt ?? ''"
                        @input="
                            emit(
                                'update:endsAt',
                                ($event.target as HTMLInputElement).value ||
                                    null,
                            )
                        "
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <div>
                <Label class="mb-1.5 block">{{
                    t('component.broadcast.schedule.timezone')
                }}</Label>
                <input
                    type="text"
                    :value="timezone"
                    @input="
                        emit(
                            'update:timezone',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                    class="w-full max-w-[260px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            </div>
        </CardContent>
    </Card>
</template>
