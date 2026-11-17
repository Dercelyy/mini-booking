import assert from 'node:assert/strict'

// Run only against the local demo. All bookings created here are cancelled in finally.
const base = process.env.API_URL || 'http://127.0.0.1:8000'
const fetchApi = (path, options = {}) => fetch(`${base}/api${path}`, { ...options, signal: AbortSignal.timeout(15000) })
const scheduleResponse = await fetchApi('/slots')
assert.equal(scheduleResponse.status, 200, 'API must be healthy before running this check.')
const schedule = await scheduleResponse.json()
const slot = schedule.data.find(slot => slot.status === 'available')
assert.ok(slot, 'Seed the database first; a free future slot is required.')
const created = []
try {
  const outcomes = await Promise.allSettled(Array.from({ length: 10 }, () => fetchApi('/bookings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ slot_id: slot.id, customer_name: 'Concurrency check', customer_email: 'check@example.com' }),
  })))
  const responses = outcomes.filter(outcome => outcome.status === 'fulfilled').map(outcome => outcome.value)
  for (const response of responses) {
    if (response.status === 201) created.push((await response.json()).id)
  }
  assert.equal(responses.length, 10, 'All requests must receive an HTTP response.')
  assert.equal(responses.filter(response => response.status === 201).length, 1)
  assert.equal(responses.filter(response => response.status === 409).length, 9)
  const updated = await fetchApi('/slots').then(response => response.json())
  assert.equal(updated.data.find(item => item.id === slot.id).booking_id, created[0])
  console.log('PASS: 10 concurrent requests → one 201, nine 409, one active booking.')
} finally {
  for (const id of created) {
    const response = await fetchApi(`/bookings/${id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
    assert.equal(response.status, 204)
  }
}
