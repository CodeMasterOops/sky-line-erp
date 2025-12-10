import moment from "moment";

// eslint-disable-next-line no-undef
const dateFunctions = NepaliFunctions;

export const useDateHelper = () => {

    const currentBsDate = () => {
        try {
            return dateFunctions.BS.GetCurrentDate('YYYY-MM-DD')
        } catch {
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

    const adToBs = (ad_date) => {
        if (ad_date) {
            try {
                return dateFunctions.AD2BS(ad_date,"YYYY-MM-DD");
            } catch (e) {
                console.log(e)
            }
        }
    }

    const bsToAd = (bs_date) => {
        if (bs_date) {
            try {
                const bsDateObj = (dateFunctions.ParseDate(bs_date)).parsedDate
                const adDateObj = dateFunctions.BS2AD({
                    year: bsDateObj.year,
                    month: bsDateObj.month,
                    day: bsDateObj.day
                })

                return dateFunctions.ConvertDateFormat({
                    year: adDateObj.year,
                    month: adDateObj.month,
                    day: adDateObj.day
                }, "YYYY-MM-DD")
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

    const nepaliDate=(date='')=>{
        date=date ? date : currentBsDate();

        return `${dateFunctions.BS.GetFullDate(date, true, "YYYY-MM-DD")} ${dateFunctions.BS.GetFullDayInUnicode(date)}`;
    }

    const formatDateToYMD = (date) => {
        return moment(date).format('YYYY-MM-DD');
    }

    const currentTime = () => {
        return moment().format('HH:mm A')
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
        toUniCode,nepaliDate:nepaliDate(),
        currentBsYear, currentAdYear,
        currentBsMonth,
        bsMonths
    }
}
