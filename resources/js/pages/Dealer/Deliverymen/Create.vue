<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, AtSign, User, Phone, KeyRound, Truck } from 'lucide-vue-next';
import { toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import UsernameStatusBadge from '@/components/UsernameStatusBadge.vue';
import { useUsernameAvailability } from '@/composables/useUsernameAvailability';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

const form = useForm({
    name: '',
    username: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const { status: usernameStatus } = useUsernameAvailability(toRef(form, 'username'));

function submit() {
    form.post('/dealer/deliverymen');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.deliverymen.createHead')" />

    <div class="mx-auto w-full max-w-4xl p-4 md:p-8">
        <!-- Header -->
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/dealer/deliverymen')">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.deliverymen.createTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.deliverymen.createSubtitle') }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Shaxsiy ma'lumotlar -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Truck class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.deliverymen.personalInfo') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('pageDealer.deliverymen.personalInfoDesc') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="space-y-5 p-6">
                        <div>
                            <Label for="name" class="mb-1.5 flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ t('pageDealer.deliverymen.fullName') }} <span class="text-destructive">*</span>
                            </Label>
                            <Input id="name" v-model="form.name" :placeholder="t('pageDealer.deliverymen.fullNamePlaceholder')" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="username" class="mb-1.5 flex items-center gap-1.5">
                                    <AtSign class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ t('pageDealer.common.username') }} <span class="text-destructive">*</span>
                                </Label>
                                <Input id="username" v-model="form.username" autocomplete="username" :placeholder="t('pageDealer.deliverymen.usernamePlaceholder')" required />
                                <UsernameStatusBadge :status="usernameStatus" />
                                <InputError :message="form.errors.username" />
                            </div>

                            <div>
                                <Label for="phone" class="mb-1.5 flex items-center gap-1.5">
                                    <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ t('pageDealer.common.phone') }} <span class="text-destructive">*</span>
                                </Label>
                                <PhoneInput v-model="form.phone" :error="!!form.errors.phone" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Parol -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <div class="flex items-start gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <KeyRound class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ t('pageDealer.deliverymen.loginPassword') }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ t('pageDealer.deliverymen.loginPasswordDesc') }}
                            </p>
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-2">
                    <CardContent class="grid gap-4 p-6 sm:grid-cols-2">
                        <div>
                            <Label for="password" class="mb-1.5">{{ t('pageDealer.common.password') }} <span class="text-destructive">*</span></Label>
                            <Input id="password" v-model="form.password" type="password" required />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <Label for="password_confirmation" class="mb-1.5">{{ t('pageDealer.common.passwordRepeat') }} <span class="text-destructive">*</span></Label>
                            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Action bar -->
            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-3 border-t bg-background/80 px-4 py-4 backdrop-blur md:-mx-8 md:px-8">
                <Button variant="outline" type="button" @click="router.get('/dealer/deliverymen')">{{ t('pageDealer.common.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing" class="min-w-[140px]">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageDealer.deliverymen.saving') : t('pageDealer.deliverymen.addBtn') }}
                </Button>
            </div>
        </form>
    </div>
</template>
