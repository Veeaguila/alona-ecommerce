import test from 'node:test';
import assert from 'node:assert/strict';
import { logisticsSessionFetch } from '../../resources/js/logisticsSession.js';

const origin = 'http://localhost:8000';
test('expired Logistics API responses recover once and leave the response readable', async () => {
    let expired = 0;
    const fetch = logisticsSessionFetch(async () => new Response('{"message":"Unauthenticated."}', { status: 401 }),
        { origin, getToken: () => 'current', onExpired: () => expired++ });
    const options = { headers: { Authorization: 'Bearer current' } };
    const response = await fetch('/api/sorting-center/dashboard', options);
    assert.equal((await response.json()).message, 'Unauthenticated.');
    await fetch('/api/sorting-center/hub-workspace', options);
    assert.equal(expired, 1);
});

test('public, foreign, other-token and forbidden requests cannot sign out a Logistics session', async () => {
    let expired = 0;
    const fetch = logisticsSessionFetch(async () => new Response(null, { status: 401 }),
        { origin, getToken: () => 'current', onExpired: () => expired++ });
    await fetch('/api/sorting-center/login');
    await fetch('/api/user', { headers: { Authorization: 'Bearer old' } });
    await fetch('https://example.test/api/user', { headers: { Authorization: 'Bearer current' } });
    await fetch('/logout', { headers: { Authorization: 'Bearer current' } });
    const denied = logisticsSessionFetch(async () => new Response(null, { status: 403 }),
        { origin, getToken: () => 'current', onExpired: () => expired++ });
    await denied('/api/sorting-center/dashboard', { headers: { Authorization: 'Bearer current' } });
    assert.equal(expired, 0);
});

test('late unauthorized responses from an old login never clear a newer login', async () => {
    let current = 'old', resolve, expired = 0;
    const fetch = logisticsSessionFetch(() => new Promise(done => { resolve = done; }),
        { origin, getToken: () => current, onExpired: () => expired++ });
    const pending = fetch('/api/user', { headers: { Authorization: 'Bearer old' } });
    current = 'new'; resolve(new Response(null, { status: 401 })); await pending;
    assert.equal(expired, 0);
});

test('Request inputs retain their headers and successful requests do not trigger recovery', async () => {
    let expired = 0;
    const request = new Request(origin + '/api/user', { headers: { Authorization: 'Bearer current' } });
    const fetch = logisticsSessionFetch(async input => { assert.equal(input, request); return new Response(null, { status: 200 }); },
        { origin, getToken: () => 'current', onExpired: () => expired++ });
    assert.equal((await fetch(request)).status, 200);
    assert.equal(expired, 0);
});
