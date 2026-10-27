const initialisePoll = (root) => {
    if (root.dataset.ready === 'true') return
    root.dataset.ready = 'true'
    const form = root.querySelector('[data-poll-form]')
    if (!form) return
    let bootstrap
    let submitting = false
    const status = root.querySelector('[data-poll-status]')
    const results = root.querySelector('[data-poll-results]')
    // Awards panels and ordinary widgets must establish the same session.
    const bootstrapQueue = (document.capellLivePollBootstrapQueue ??= {
        pending: null,
    })
    const start = () => {
        if (bootstrap) return bootstrap
        const request = () =>
            fetch(root.dataset.bootstrapUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            }).then((response) => {
                if (!response.ok) throw new Error('bootstrap')
                return response.json()
            })
        const pending = bootstrapQueue.pending
            ? bootstrapQueue.pending.then(request)
            : request()
        // A failed poll must not block the next client in the queue.
        bootstrapQueue.pending = pending.catch(() => {})
        bootstrap = pending.catch((error) => {
            bootstrap = null
            throw error
        })
        return bootstrap
    }
    const showResults = (counts) => {
        if (!counts) return
        Object.entries(counts).forEach(([id, count]) => {
            const row = Array.from(
                root.querySelectorAll('[data-option-result]'),
            ).find((candidate) => candidate.dataset.optionResult === id)
            if (row) row.querySelector('strong').textContent = String(count)
        })
        results.hidden = false
    }
    form.addEventListener(
        'change',
        () => {
            start()
                .then((state) => {
                    if (state.has_voted) {
                        form.hidden = true
                        showResults(state.results)
                        status.textContent = root.dataset.messageDuplicate
                    }
                })
                .catch(() => {
                    status.textContent = root.dataset.messagePrepareError
                })
        },
        { once: true },
    )
    form.addEventListener('submit', (event) => {
        event.preventDefault()
        if (submitting || form.hidden) return
        const option = new FormData(form).get('option_id')
        if (typeof option !== 'string') return
        submitting = true
        start()
            .then((state) => {
                if (state.has_voted)
                    return { accepted: false, results: state.results }
                return fetch(root.dataset.voteUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': state.csrf_token,
                    },
                    body: JSON.stringify({ option_id: option }),
                }).then((response) => {
                    if (response.status === 419) bootstrap = null
                    if (!response.ok) throw new Error('vote')
                    return response.json()
                })
            })
            .then((result) => {
                form.hidden = true
                showResults(result.results)
                status.textContent = result.accepted
                    ? root.dataset.messageCounted
                    : root.dataset.messageDuplicate
            })
            .catch(() => {
                status.textContent = root.dataset.messageError
            })
            .finally(() => {
                submitting = false
            })
    })
}
document
    .querySelectorAll('[data-capell-widget-live-poll]')
    .forEach(initialisePoll)
document.addEventListener('capell:content-ready', (event) =>
    event.target
        ?.querySelectorAll?.('[data-capell-widget-live-poll]')
        .forEach(initialisePoll),
)
