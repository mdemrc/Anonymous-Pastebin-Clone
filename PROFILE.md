# Anonymous Pastebin Clone — Project Description

## English

Anonymous Pastebin Clone is a self-hosted, anonymity-respecting Pastebin alternative written in PHP and MySQL. It provides the canonical Pastebin experience — write, share, view raw, search — through a fast, account-free public path, and layers an optional account system on top for users who want a profile page, pinned pastes, and a like/dislike feed. Pastes can be marked public, unlisted, or private with a per-paste password hash, and a lightweight admin area covers banner copy, role flags, and abuse tooling.

The project is intentionally kept "boring" on the runtime side: there is no JavaScript build pipeline, no client-side framework, and no third-party service dependency. Every page is server-rendered PHP, every query is parameterised, and the codebase fits comfortably in a single host. The schema separates anonymous pastes from authored pastes, so anonymity is preserved by default and accounts are an additive, opt-in feature.

The repository is published as a code-review artefact: a compact end-to-end example of a useful internet utility implemented with the simplest possible production stack. It is a good lens on how I think about minimum viable surface area, server-side rendering trade-offs, and database design for content-heavy products.

## Türkçe

Anonymous Pastebin Clone; PHP ve MySQL ile yazılmış, anonimliği gözeten ve kendi sunucunuzda barındırılabilen bir Pastebin alternatifidir. Pastebin'in klasik deneyimini — yazma, paylaşma, raw görüntüleme, arama — hesap gerektirmeyen hızlı bir genel yol üzerinden sunar; profil sayfası, sabitlenmiş paste'ler ve beğeni/beğenmeme akışı isteyen kullanıcılar için bunun üzerine isteğe bağlı bir hesap sistemi ekler. Paste'ler genel, listelenmemiş veya paste başına şifre hash'i ile özel olarak işaretlenebilir; hafif bir yönetici alanı banner metni, rol bayrakları ve kötüye kullanım araçlarını barındırır.

Proje çalışma zamanı tarafında kasıtlı olarak "sıkıcı" tutulmuştur: JavaScript derleme hattı, istemci tarafı çerçevesi veya üçüncü taraf bir servis bağımlılığı yoktur. Her sayfa sunucu tarafında oluşturulan PHP, her sorgu parametreli ve kod tabanı tek bir hosta rahatlıkla sığar. Şema; anonim paste'leri yazarlı paste'lerden ayırır, böylece anonimlik varsayılan olarak korunur ve hesaplar eklenti niteliğinde, isteğe bağlı bir özellik olarak kalır.

Repo, bir kod inceleme nesnesi olarak yayımlanmıştır: faydalı bir internet aracını mümkün olan en sade üretim yığınıyla uçtan uca uygulamış kompakt bir örnek. Minimum uygulanabilir yüzey alanı, sunucu tarafı render dengeleri ve içerik ağırlıklı ürünler için veritabanı tasarımı hakkındaki düşünme biçimimi göstermek için iyi bir mercektir.
