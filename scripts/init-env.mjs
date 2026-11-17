import { randomBytes } from 'node:crypto'
import { existsSync, readFileSync, writeFileSync } from 'node:fs'

if (existsSync('.env')) {
  console.log('.env already exists; preserved without changes.')
} else {
  const template = readFileSync('.env.example', 'utf8')
  writeFileSync('.env', template
    .replace('DB_PASSWORD=', `DB_PASSWORD=${randomBytes(24).toString('hex')}`)
    .replace('APP_KEY=', `APP_KEY=base64:${randomBytes(32).toString('base64')}`), { mode: 0o600 })
  console.log('Created .env with random local credentials.')
}
