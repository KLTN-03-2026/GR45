export function valueOr(value, fallback) {
  return value == null ? fallback : value;
}

export function textOrEmpty(value) {
  return String(valueOr(value, ""));
}

export function errorText(error) {
  return textOrEmpty(error?.message == null ? error : error.message);
}

export function isFunction(value) {
  return value?.constructor === Function;
}

export function isObject(value) {
  return value?.constructor === Object;
}

export function isString(value) {
  return value?.constructor === String;
}

export function isNumber(value) {
  return value?.constructor === Number;
}

export function anyTrue(...values) {
  return values.some(Boolean);
}
