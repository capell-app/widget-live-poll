import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import vm from 'node:vm'

const source = readFileSync(
    new URL('../../resources/dist/widget.js', import.meta.url),
    'utf8',
)
const flush = () => new Promise((resolve) => setImmediate(resolve))
function poll(fetch) {
    const listeners = {}
    const form = {
        hidden: false,
        addEventListener: (name, callback) => {
            listeners[name] = callback
        },
    }
    const status = { textContent: '' }
    const results = { hidden: true }
    const root = {
        dataset: {
            bootstrapUrl: '/prepare',
            voteUrl: '/vote',
            messagePrepareError: 'retry preparation',
            messageError: 'retry vote',
            messageCounted: 'counted',
            messageDuplicate: 'duplicate',
        },
        querySelector: (selector) =>
            ({
                '[data-poll-form]': form,
                '[data-poll-status]': status,
                '[data-poll-results]': results,
            })[selector],
        querySelectorAll: () => [],
    }
    vm.runInNewContext(source, {
        document: { querySelectorAll: () => [root], addEventListener() {} },
        fetch,
        FormData: class {
            get() {
                return '7'
            }
        },
    })
    return {
        form,
        status,
        results,
        change: () => listeners.change(),
        submit: () => listeners.submit({ preventDefault() {} }),
    }
}
const response = (body, ok = true) => ({ ok, json: async () => body })
for (const failure of ['network', 'non-OK', 'invalid JSON']) {
    test(`a ${failure} preparation failure can be retried with one vote`, async () => {
        const calls = []
        const widget = poll(async (url, options) => {
            calls.push({ url, options })
            if (calls.length === 1) {
                if (failure === 'network') throw new Error('offline')
                if (failure === 'invalid JSON')
                    return {
                        ok: true,
                        json: async () => {
                            throw new Error('invalid')
                        },
                    }
                return response({}, false)
            }
            return response(
                url === '/prepare'
                    ? { csrf_token: 'fresh', has_voted: false }
                    : { accepted: true, results: {} },
            )
        })
        widget.change()
        await flush()
        assert.equal(widget.status.textContent, 'retry preparation')
        widget.submit()
        await flush()
        assert.deepEqual(
            calls.map(({ url }) => url),
            ['/prepare', '/prepare', '/vote'],
        )
        assert.equal(calls[2].options.headers['X-CSRF-TOKEN'], 'fresh')
        assert.equal(widget.status.textContent, 'counted')
    })
}
test('concurrent submissions coalesce preparation and send one vote', async () => {
    let complete
    const calls = []
    const widget = poll((url) => {
        calls.push(url)
        return url === '/prepare'
            ? new Promise((resolve) => {
                  complete = resolve
              })
            : Promise.resolve(response({ accepted: true, results: {} }))
    })
    widget.change()
    widget.submit()
    widget.submit()
    complete(response({ csrf_token: 'fresh', has_voted: false }))
    await flush()
    assert.deepEqual(calls, ['/prepare', '/vote'])
})
test('already-voted preparation displays results without posting again', async () => {
    const calls = []
    const widget = poll(async (url) => {
        calls.push(url)
        return response({ has_voted: true, results: {} })
    })
    widget.submit()
    await flush()
    assert.deepEqual(calls, ['/prepare'])
    assert.equal(widget.status.textContent, 'duplicate')
    assert.equal(widget.form.hidden, true)
    assert.equal(widget.results.hidden, false)
})
test('a failed vote stays retryable and never claims acceptance', async () => {
    let votes = 0
    const widget = poll(async (url) =>
        url === '/prepare'
            ? response({ csrf_token: 'fresh', has_voted: false })
            : response({ accepted: ++votes > 1, results: {} }, votes > 1),
    )
    widget.submit()
    await flush()
    assert.equal(widget.form.hidden, false)
    assert.equal(widget.results.hidden, true)
    assert.equal(widget.status.textContent, 'retry vote')
    widget.submit()
    await flush()
    assert.equal(widget.status.textContent, 'counted')
})
