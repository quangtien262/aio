import { useMemo } from 'react';
import Select from 'antd/es/select';
import { manufacturingTimezoneOption, manufacturingTimezoneOptions, timezoneSearchText } from '../utils/manufacturingTimezones.js';

export default function ManufacturingTimezoneSelect({ value, timezones, ...props }) {
    const baseOptions = useMemo(() => manufacturingTimezoneOptions(timezones), [timezones]);
    const options = useMemo(() => value && !baseOptions.some((item) => item.value === value)
        ? [...baseOptions, manufacturingTimezoneOption(value)] : baseOptions, [baseOptions, value]);
    return <Select {...props} value={value} options={options} showSearch placeholder="Chọn múi giờ"
        filterOption={(input, option) => option.searchText.includes(timezoneSearchText(input))}
        notFoundContent="Không tìm thấy múi giờ phù hợp" style={{ width: '100%' }} />;
}
