import moment from "moment";
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

    const currentAdDate = moment().format('YYYY-MM-DD');

    const subAdYears = (year) => {
        const subtractedYearDate = moment().subtract(year, 'years')

        return formatDateToYMD(subtractedYearDate);
    }

    const subBsYears = (year) => {
        const adjustedDate = moment().subtract(year, 'years')

        return adToBs(formatDateToYMD(adjustedDate));
    }

    const subBsDays = (days) => {
        const adjustedDate = moment().subtract(days, 'days')

        return adToBs(formatDateToYMD(adjustedDate));
    }

    const addBsDays = (days) => {
        const adjustedDate = moment().add(days, 'days')

        return adToBs(formatDateToYMD(adjustedDate));
    }

    const subAdDays = (days) => {
        const adjustedDate = moment().subtract(days, 'days')

        return formatDateToYMD(adjustedDate);
    }

    const addAdDays = ({date = '', days}) => {
        const parsedDate = date ? moment(date) : moment();
        const adjustedDate = parsedDate.add(days, 'days')

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
        return moment(date).format('YYYY-MM-DD');
    }

    const currentTime = () => {
        return moment().format('HH:mm A')
    }

    const generateDateRange = (startDate, endDate) => {
        const start = moment(startDate);
        const end = moment(endDate);
        const dates = [];

        while (start <= end) {
            dates.push(start.format("YYYY-MM-DD"));
            start.add(1, "days");
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
