# Typesense Sync

An Omeka S module that triggers a rebuild of an external
[Typesense](https://typesense.org) search index from the Omeka admin.

Omeka is the system of record; a Typesense collection is a derived copy that a
search UI queries. Something has to tell that copy it is out of date. This module
is the button that does it — it holds the address of your rebuild endpoint, the
credential for it, and sends the request.

**It does not talk to Typesense.** It posts to an endpoint you run, which does
the actual indexing. That indirection is the point: the reindexing logic, the
Typesense admin key and the collection schema stay outside Omeka, and Omeka
holds nothing but a URL and one scoped credential.

## Features

- An admin page with a button that triggers a reindex
- Endpoint URL and API key set from the module configuration form
- Records when the last sync ran
- Reports what the endpoint returned, when it returns a recognised shape

## Installation

1. Copy the `TypesenseSync` directory into your Omeka S `modules/` directory.
2. In the Omeka S admin, go to **Modules**, find **Typesense Sync**, and click
   **Install**.
3. Click **Configure** and set the endpoint and API key.

## Configuration

**Modules → Typesense Sync → Configure.** Both settings are blank on a new
install; there is no default, because a default would be somebody else's
endpoint.

| Setting | Meaning |
|---|---|
| Sync endpoint URL | receives an empty `POST` when a sync is triggered |
| API key | sent as the `x-api-key` header, if set |

The stored key is **never sent back to the browser.** The field posts a new
value, or, left blank, keeps the one already stored. Removing a key is a separate
explicit checkbox, since blank already means "unchanged".

The module's own admin page is read-only apart from the trigger button. Settings
are written from the configuration form and nowhere else, so there is one code
path handling the credential rather than two.

## The endpoint contract

Deliberately minimal — one request, no body:

```
POST <your endpoint>
Content-Type: application/json
x-api-key: <your key>        (omitted when no key is set)
```

Any 2xx counts as success. Anything else is surfaced in the admin with its status
code, and the timeout is 120 seconds.

If the response body is JSON containing `collections` and `items`, they are
reported back in the success message, with `duration_seconds` if present:

```json
{ "collections": 3, "items": 1462, "duration_seconds": 12.4 }
```

Any other body is treated as success without detail. **The body is never echoed
into the page** — it arrives from a URL an administrator typed, so it is not
trusted content.

Nothing about this is Typesense-specific. Any endpoint that accepts a signed
POST and rebuilds an index satisfies it.

## Requirements

- Omeka S 4.0 or higher
- An endpoint that rebuilds your index, reachable from the Omeka host

## Permissions

The module registers no ACL rules, so Omeka S's default applies: **Global
Administrators only.** That is deliberate and worth stating, because the page
both discloses whether a credential is set and controls where an authenticated
request is sent. Granting other roles access would mean letting them repoint that
request.

## Known limitations

Current characteristics, not bugs in disguise.

- **Nothing is automatic.** The index is rebuilt when somebody presses the
  button. Editing an item does not trigger it. Hooking `api.update.post` is the
  obvious next feature and is not implemented.
- **It rebuilds everything.** There is no per-item or incremental sync — the
  endpoint is called with no arguments and decides its own scope.
- **One endpoint.** A single URL, not a list, and no per-collection routing.
- **No retry.** A failure reports its status code and is forgotten. There is no
  queue and no backoff.
- **Synchronous, with a 120-second timeout.** The admin request blocks while the
  endpoint works. An index that takes longer than that will report a timeout
  even though the rebuild may well finish.
- **The key is stored in Omeka's settings table**, which is to say in the
  database in plain text, like every other Omeka setting. It is kept out of the
  page and out of logs; it is not encrypted at rest.

## Provenance

Extracted from a private monorepo where it has run in production against
Omeka S 4.x. Deployment-specific values were removed rather than parameterised,
and the history here starts at extraction.

Extraction was preceded by a security pass on the private copy, which is why
this one arrives with the credential kept off the page, a CSRF token on the
trigger, Global-Administrator-only access, and without the unauthenticated-shaped
JSON proxy endpoint the original carried. None of those flaws ever existed in
this repository, and it seemed more useful to say so than to imply the module was
always this way.

## License

GPL-3.0-or-later, matching Omeka S itself — a module is a derivative work of the
application it extends. See [LICENSE](LICENSE).
