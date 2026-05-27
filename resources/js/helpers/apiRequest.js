/**
 * @param {import('axios').AxiosInstance} client
 * @param {string} method
 * @param {string} url
 * @param {object|null} body
 * @param {import('axios').AxiosRequestConfig} config
 */
export function formattedRequest(client, method, url, body = null, config = {}) {
    switch (method) {
        case 'post': {
            return body ? client.post(url, body, config) : client.post(url, config);
        }
        case 'put': {
            return body ? client.put(url, body, config) : client.put(url, config);
        }
        case 'delete': {
            return client.delete(url, config);
        }
        default:
            return body
                ? client.get(url, {params: body, ...config})
                : client.get(url, config);
    }
}
