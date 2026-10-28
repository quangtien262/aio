const aliases = { 'mã hàng': 'code', 'tên hàng': 'name', 'đơn vị': 'base_unit', 'giá bán': 'price_minor', 'số đăng ký': 'registration_number', 'hoạt chất': 'ingredients', 'hàm lượng': 'strength', 'dạng bào chế': 'dosage_form', 'bán theo đơn': 'requires_prescription', 'kiểm soát đặc biệt': 'controlled', 'chuỗi lạnh': 'cold_chain' };
const allowed = new Set([...Object.values(aliases), 'minimum_increment']);
export function parseProductImport(text) {
    const source = String(text ?? '').replace(/^\uFEFF/, '');
    if (source.length > 200000) throw new Error('Bảng quá lớn. Chia thành tối đa 100 hàng mỗi lần.');
    const delimiter = source.split(/\r?\n/, 1)[0].includes('\t') ? '\t' : ',';
    const rows = []; let row = []; let cell = ''; let quoted = false;
    for (let i = 0; i < source.length; i++) {
        const char = source[i];
        if (char === '"') {
            if (quoted && source[i + 1] === '"') { cell += '"'; i++; }
            else if (quoted || cell === '') quoted = !quoted;
            else throw new Error('Dấu ngoặc kép không đúng định dạng CSV.');
        } else if (char === delimiter && !quoted) { row.push(cell); cell = ''; }
        else if ((char === '\n' || char === '\r') && !quoted) {
            if (char === '\r' && source[i + 1] === '\n') i++;
            row.push(cell); if (row.some((value) => value.trim())) rows.push(row); row = []; cell = '';
        } else cell += char;
    }
    if (quoted) throw new Error('Bảng còn một ô chưa đóng ngoặc kép.');
    row.push(cell); if (row.some((value) => value.trim())) rows.push(row);
    const headers = (rows.shift() ?? []).map((value) => aliases[value.trim().toLowerCase()] ?? value.trim().toLowerCase());
    if (!['code', 'name', 'base_unit', 'price_minor'].every((key) => headers.includes(key))) throw new Error('Cần các cột Mã hàng, Tên hàng, Đơn vị và Giá bán.');
    if (new Set(headers).size !== headers.length || headers.some((key) => !allowed.has(key))) throw new Error('Cột bị trùng hoặc chưa được hỗ trợ. Dùng mẫu được cung cấp.');
    if (!rows.length || rows.length > 100) throw new Error('Mỗi lần nhập từ 1 đến 100 hàng.');
    return rows.map((values, index) => {
        if (values.length !== headers.length) throw new Error(`Dòng ${index + 2} có số cột không khớp tiêu đề.`);
        return Object.fromEntries(headers.map((key, column) => {
            const value = values[column].trim();
            if (['requires_prescription', 'controlled', 'cold_chain'].includes(key)) {
                if (!['', '0', '1', 'true', 'false', 'có', 'không'].includes(value.toLowerCase())) throw new Error(`Dòng ${index + 2}: cột phân loại dùng Có / Không.`);
                return [key, ['1', 'true', 'có'].includes(value.toLowerCase())];
            }
            return [key, value];
        }));
    });
}
