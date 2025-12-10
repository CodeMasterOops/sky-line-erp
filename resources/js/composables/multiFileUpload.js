import {ref} from "vue";
import pdfIcon from '@/assets/images/pdf_icon.png';

export const useMultiFileUpload = () => {

    const imageExtensions = ['image/jpeg', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
    const pdfExtensions = ['application/pdf'];

    const files = ref([]);

    const onFileSelected = (event) => {
        const fileList = event.target.files;
        if (fileList.length === 0) {
            files.value = [];
            return;
        }

        Array.from(fileList).forEach((f) => {
            if (f instanceof File) {
                let imageUrl = null;

                if (imageExtensions.includes(f.type)) {
                    let fileReader = new FileReader();
                    fileReader.readAsDataURL(f);
                    fileReader.addEventListener("load", () => {
                        imageUrl = fileReader.result;
                        files.value.push({
                            file: f,
                            name: f.name,
                            imageUrl: imageUrl,
                            size: (f.size / 1000).toFixed(2),
                        });
                    });
                } else if (pdfExtensions.includes(f.type)) {
                    imageUrl = pdfIcon;
                    files.value.push({
                        file: f,
                        name: f.name,
                        imageUrl: imageUrl,
                        size: (f.size / 1000).toFixed(2),
                    });
                }
            }
        });
    };


    const onMediaFileSelected = (media) => {
        files.value = [];
        files.value.push({
            file: media.path,
            name: media.file_name,
            imageUrl: media.file_url,
            size: media.file_size,
            image_alt: media.image_alt,
        })
    }

    const resetFile = () => {
        files.value = [];
    }

    return {
        files,
        onFileSelected,
        onMediaFileSelected,
        resetFile
    }
}
