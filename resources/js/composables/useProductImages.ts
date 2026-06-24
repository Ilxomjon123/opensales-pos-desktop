import { ref } from 'vue';

export type ImagePreview = { file: File; url: string };

export function useProductImages(maxImages: number = 10) {
    const imagePreviews = ref<ImagePreview[]>([]);

    function add(e: Event) {
        const files = (e.target as HTMLInputElement).files;

        if (!files) {
return;
}

        for (const file of Array.from(files)) {
            if (imagePreviews.value.length >= maxImages) {
break;
}

            imagePreviews.value.push({ file, url: URL.createObjectURL(file) });
        }

        (e.target as HTMLInputElement).value = '';
    }

    function remove(idx: number) {
        const preview = imagePreviews.value[idx];

        if (!preview) {
return;
}

        URL.revokeObjectURL(preview.url);
        imagePreviews.value.splice(idx, 1);
    }

    function makePrimary(idx: number) {
        if (idx <= 0) {
return;
}

        const [picked] = imagePreviews.value.splice(idx, 1);

        if (picked) {
            imagePreviews.value.unshift(picked);
        }
    }

    function files(): File[] {
        return imagePreviews.value.map((p) => p.file);
    }

    return {
        imagePreviews,
        add,
        remove,
        makePrimary,
        files,
        maxImages,
    };
}
