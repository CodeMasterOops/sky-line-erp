import dayjs from "dayjs";
import {convertToNepali} from "@/helpers/helper.js";

// eslint-disable-next-line no-undef
const dateFunctions = NepaliFunctions;

export const useDateHelper = () => {

    const currentBsDate = () => {
        try {
            return dateFunctions.BS.GetCurrentDate('YYYY-MM-DD')
        } catch (e) {
            return '';
        }
    }

    const currentAdDate = dayjs().format('YYYY-MM-DD');

    const subAdYears = (year) => {
        const subtractedYearDate = dayjs().subtract(year, 'year')

        return formatDateToYMD(subtractedYearDate);
    }

    const subBsYears = (year) => {
        const adjustedDate = dayjs().subtract(year, 'year')

        return adToBs(formatDateToYMD(adjustedDate));
    }

    const subBsDays = (days) => {
        const adjustedDate = dayjs().subtract(days, 'day')

        return adToBs(formatDateToYMD(adjustedDate));
    }

    const addBsDays = (days) => {
        const adjustedDate = dayjs().add(days, 'day')

        return adToBs(formatDateToYMD(adjustedDate));
    }

    const subAdDays = (days) => {
        const adjustedDate = dayjs().subtract(days, 'day')

        return formatDateToYMD(adjustedDate);
    }

    const addAdDays = ({date = '', days}) => {
        const parsedDate = date ? dayjs(date) : dayjs();
        const adjustedDate = parsedDate.add(days, 'day')

        return formatDateToYMD(adjustedDate);
    }

    const adToBs = (ad_date, format = 'en') => {
        if (ad_date) {
            try {
                const bsDate = dateFunctions.AD2BS(ad_date, "YYYY-MM-DD");

                if (format === 'ne') {
                    return convertToNepali(bsDate);
                }

                return bsDate;
            } catch (e) {
                console.log(e)
            }
        }
    }

    const bsToAd = (bs_date) => {
        if (bs_date) {
            try {
                return dateFunctions.BS2AD(bs_date, "YYYY-MM-DD");
            } catch (e) {
                console.log(e);
            }
        }
    }

    const getDay = () => {
        let N = new Date(2023, 4 - 1, 26)
        return N.getDay();
    }

    const toUniCode = (num) => {
        return dateFunctions.ConvertToUnicode(num);
    }

    const currentBsYear = dateFunctions.BS.GetCurrentYear();

    const currentAdYear = dateFunctions.BS.GetCurrentYear();

    const currentBsMonth = () => {
        const month = dateFunctions.GetCurrentBsMonth()
        return month < 10 ? '0' + month : month;
    }

    const bsMonths = dateFunctions.BS.GetMonths();

    const nepaliDate = (date = '') => {
        date = date ? date : currentBsDate();

        return `${dateFunctions.BS.GetFullDate(date, true, "YYYY-MM-DD")} ${dateFunctions.BS.GetFullDayInUnicode(date)}`;
    }

    const formatDateToYMD = (date) => {
        return dayjs(date).format('YYYY-MM-DD');
    }

    const currentTime = () => {
        return dayjs().format('HH:mm A')
    }

    const generateDateRange = (startDate, endDate) => {
        let start = dayjs(startDate);
        const end = dayjs(endDate);
        const dates = [];

        while (!start.isAfter(end)) {
            dates.push(start.format("YYYY-MM-DD"));
            start = start.add(1, "day");
        }

        return dates;
    }

    return {
        currentBsDate: currentBsDate(),
        currentAdDate,
        yesterdayDate: subAdDays(1),
        currentTime: currentTime(),
        subBsYears, subAdYears,
        subBsDays, addBsDays,
        subAdDays, addAdDays,
        bsToAd, adToBs,
        getDay,
        toUniCode, nepaliDate: nepaliDate(),
        currentBsYear, currentAdYear,
        currentBsMonth,
        bsMonths,
        generateDateRange
    }
}
