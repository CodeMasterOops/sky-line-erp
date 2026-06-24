import {useDisplayDate} from "@/composables/useDisplayDate.js";

/**
 * Preference-aware label helper for the `daterangepicker` widget.
 *
 * The picker itself stays a Gregorian calendar; this only formats the readonly
 * text it writes into the input, showing BS dates when the user prefers BS.
 * Pair with `watch(mode, syncPicker)` so the text updates on a calendar toggle.
 */
export const useRangePickerLabel = () => {
    const {mode, formatDate} = useDisplayDate();

    const formatRangeDate = (m, adFormat = 'DD-MM-YYYY') =>
        formatDate(m.format('YYYY-MM-DD'), {adFormat});

    const formatPickerValue = (startDate, endDate, adFormat = 'DD-MM-YYYY') =>
        `${formatRangeDate(startDate, adFormat)} - ${formatRangeDate(endDate, adFormat)}`;

    return {mode, formatRangeDate, formatPickerValue};
};
