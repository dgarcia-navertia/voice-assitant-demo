# Credenciales de desarrollo

> **SOLO DESARROLLO.** Estas cuentas las crea `make seed` con una contraseña publicada en
> este repositorio. No ejecutes los seeders en producción (el despliegue `prod` no lo hace) y,
> si tienes que sembrar algo en un servidor real, cambia antes `SEED_USER_PASSWORD` en `.env`.

Contraseña común de todos los usuarios sembrados (variable `SEED_USER_PASSWORD`, valor por
defecto de `.env.example`):

```
Navertia-Dev-2026!
```

| Rol | Nombre | Correo | Tienda |
|-----|--------|--------|--------|
| admin | Admin Navertia | `admin@navertia.demo` | — |
| manager | Marta Sanz | `marta.sanz@navertia.demo` | Navertia Ruzafa |
| commercial (staff 1) | Carlos Ruiz | `carlos.ruiz@navertia.demo` | Navertia Ruzafa |
| commercial (staff 2) | Elena Torres | `elena.torres@navertia.demo` | Navertia Campanar |
| commercial | Pablo Ferrer | `pablo.ferrer@navertia.demo` | Navertia Puerto |
| commercial | Lucía Gil | `lucia.gil@navertia.demo` | Navertia Campanar |

Permisos: **admin** ve todo (usuarios, tiendas, festivos, comerciales); **manager** gestiona los
comerciales y citas de su tienda; **commercial** ve su agenda y sus citas. Dial Out, Llamadas,
Leads y Clientes están disponibles para todos los roles autenticados.

Re-sembrar (`make seed`) **no** restablece la contraseña de quien ya la haya cambiado.

## Otros accesos de desarrollo

- phpMyAdmin: <http://localhost:8091> (usuario/contraseña de `DB_USER`/`DB_PASSWORD` en `.env`;
  root: `MARIADB_ROOT_PASSWORD`). No existe en producción.
- API interna (`/mcp/*`): `Authorization: Bearer $INTERNAL_API_TOKEN` (generado en tu `.env`).
