<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.30s="">
    <x-pulse::card-header name="Ma'lumotlar bazasi zaxiralari">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75" />
            </svg>
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if ($backups->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <colgroup>
                    <col />
                    <col width="0%" />
                    <col width="0%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <th>Fayl</th>
                        <th class="text-right">Hajm</th>
                        <th class="text-right">Sana</th>
                        <th class="text-right">&nbsp;</th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr wire:key="{{ $backup->name }}" class="h-10">
                            <td>
                                <div class="flex items-center gap-2 min-w-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor"
                                        class="w-4 h-4 shrink-0 text-gray-400 dark:text-gray-500">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75" />
                                    </svg>
                                    <code class="block text-xs text-gray-900 dark:text-gray-100 truncate"
                                        title="{{ $backup->name }}">{{ $backup->name }}</code>
                                </div>
                            </td>
                            <td class="text-right text-gray-700 dark:text-gray-300 font-bold tabular-nums whitespace-nowrap">
                                {{ \Illuminate\Support\Number::fileSize($backup->size, precision: 1) }}
                            </td>
                            <td class="text-right text-gray-500 dark:text-gray-400 text-sm tabular-nums whitespace-nowrap">
                                {{ $backup->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.backups.download', ['file' => $backup->name]) }}"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 dark:text-gray-500 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-800"
                                    title="Yuklab olish: {{ $backup->name }}"
                                    aria-label="Yuklab olish">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
