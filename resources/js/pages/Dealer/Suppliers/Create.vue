<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';

const { t } = useI18n();
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const form = useForm({
    name: '',
    phone: '',
    contact_person: '',
    address: '',
    note: '',
    is_active: true,
});

function submit() {
    form.post('/dealer/suppliers');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.suppliers.createHead')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon" @click="router.get('/dealer/suppliers')">
                <ArrowLeft class="h-4 w-4" />
            </Button>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.suppliers.createHead') }}</h1>
        </div>

        <Card class="max-w-2xl">
            <CardContent class="pt-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <Label for="name" class="mb-1.5">{{ t('pageDealer.suppliers.name') }} <span class="text-destructive">*</span></Label>
                        <Input id="name" v-model="form.name" autofocus maxlength="255" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="phone" class="mb-1.5">{{ t('pageDealer.suppliers.phone') }}</Label>
                            <PhoneInput v-model="form.phone" :error="!!form.errors.phone" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div>
                            <Label for="contact_person" class="mb-1.5">{{ t('pageDealer.suppliers.contactPerson') }}</Label>
                            <Input id="contact_person" v-model="form.contact_person" maxlength="255" />
                            <InputError :message="form.errors.contact_person" />
                        </div>
                    </div>

                    <div>
                        <Label for="address" class="mb-1.5">{{ t('pageDealer.suppliers.address') }}</Label>
                        <Input id="address" v-model="form.address" maxlength="500" />
                        <InputError :message="form.errors.address" />
                    </div>

                    <div>
                        <Label for="note" class="mb-1.5">{{ t('pageDealer.suppliers.note') }}</Label>
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="3"
                            maxlength="1000"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        ></textarea>
                        <InputError :message="form.errors.note" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" type="button" @click="router.get('/dealer/suppliers')">{{ t('pageDealer.suppliers.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? t('pageDealer.suppliers.saving') : t('pageDealer.suppliers.save') }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
