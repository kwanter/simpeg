# SIMPEG

SIMPEG is a human-resource information system for managing employee records and the leave and permit requests raised against them. This glossary fixes the vocabulary shared by the Izin request workflow, its approval roles, and its update modes.

## Language

### Requests and Types

**Izin**:
An employee's request for permission to be absent from work, submitted through the application and subject to approval.
_Avoid_: Permit, leave request, permission

**Jenis Izin**:
The category of an Izin, drawn from the fixed taxonomy in `App\Support\IzinType`. The category determines the approval depth and the applicable PERMA No. 7/2016 rule.
_Avoid_: Type, izin type, category

**Jenis Single-Level**:
A Jenis Izin whose approval requires only the Atasan Pimpinan, so its approval is final at that step. Currently Izin Keluar Kantor and Izin Pulang Cepat.
_Avoid_: One-level, atasan-only

**Jenis Dua-Level**:
A Jenis Izin whose approval requires the Atasan Pimpinan and then the Pimpinan. Every jenis other than the single-level pair.
_Avoid_: Two-step, multi-level

**Hari Kerja**:
A calendar day that counts as a working day when measuring Izin duration, as determined by `WorkdayService`.
_Avoid_: Business day, working day

**Nomor Surat Izin**:
The official letter number recorded against an Izin after the Atasan Pimpinan has approved it.
_Avoid_: Letter number, surat number, document number

### People

**Pegawai**:
An employee record in the organisation. A User is a separate authentication identity linked to a Pegawai.
_Avoid_: Employee, staff, user

**Atasan Pimpinan**:
The employee assigned to perform the first approval step of an Izin.
_Avoid_: Supervisor, atasan, direct supervisor

**Pimpinan**:
The employee assigned to perform the final approval step of a Jenis Dua-Level Izin.
_Avoid_: Executive, leader, boss

### Update Modes

**No-Surat Update**:
An update request that changes only the Nomor Surat Izin of an already atasan-approved Izin.
_Avoid_: Partial update, letter update

**Full Update**:
An update request that changes the substantive attributes of an Izin that is not yet verified.
_Avoid_: Complete update, regular update
