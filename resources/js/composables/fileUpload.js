import {ref} from "vue";
import pdfIcon from '@/assets/images/pdf_icon.png';

export const useFileUpload = () => {

    const imageExtensions = ['image/jpeg', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
    const pdfExtensions = ['application/pdf'];

    const file = ref('');

    const fileDetail = ref({
        file: '',
        name: '',
        imageUrl: null,
        size: ''
    })

    const onFileSelected = (event) => {
        if (event.target.files.length === 0) {
            file.value = ''
        }
        file.value = event.target.files[0]
        if (!(file.value instanceof File)) {
            return;
        }
        fileDetail.value.file = file.value
        fileDetail.value.name = file.value.name
        fileDetail.value.size = (file.value.size / 1000).toFixed(2)

        if (imageExtensions.includes(file.value['type'])) {
            let fileReader = new FileReader();
            fileReader.readAsDataURL(file.value);

            fileReader.addEventListener("load", () => {
                fileDetail.value.imageUrl = fileReader.result
            })
        } else if (pdfExtensions.includes(file.value['type'])) {
            fileDetail.value.imageUrl = pdfIcon;
        }
    }

    const onMediaFileSelected = (media) => {
        fileDetail.value.file = media.path
        fileDetail.value.name = media.file_name
        fileDetail.value.size = media.file_size
        fileDetail.value.imageUrl = media.file_url
        fileDetail.value.image_alt = media.image_alt
    }

    const resetFile = () => {
        Object.assign(fileDetail.value, {
            file: '',
            name: '',
            imageUrl: null,
            size: ''
        })
    }

    return {
        fileDetail,
        onFileSelected,
        onMediaFileSelected,
        resetFile
    }
}
