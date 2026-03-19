# TypeScript Setup

This project now has a minimal TypeScript setup for browser-based scripts without changing the existing PHP/XAMPP flow.

## What was added

- `package.json` for TypeScript tooling
- `tsconfig.json` for browser compilation
- `ADMIN/assets/admin-enhancements.ts` as an admin typed source file
- `USER/assets/user-enhancements.ts` as a user typed source file

## First-time install

1. Install Node.js LTS
2. Open a terminal in `C:\xampp\htdocs\rbjsystem`
3. Run `npm install`

## Useful commands

- `npm run build` compiles `.ts` files into `.js`
- `npm run watch` recompiles automatically while you edit
- `npm run typecheck` checks types without writing files

## Current workflow

`ADMIN/assets/admin-enhancements.js` and `USER/assets/user-enhancements.js` remain the runtime files loaded by the PHP pages.

After you install Node.js and run `npm install`, edit your `.ts` files and then run `npm run build` to refresh the JavaScript output.

## Recommended next migration targets

- Move more reusable admin scripts into external `.ts` files
- Extract large inline scripts from `USER/customize.php` and related 3D pages into dedicated TypeScript modules
- Keep PHP templates focused on markup and data output only
