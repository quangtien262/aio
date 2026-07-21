export function toAppTerminology(value) {
    return typeof value === 'string'
        ? value.replace(/\bmodules?\b/gi, 'App').replace(/mô-đun/gi, 'App')
        : value;
}
