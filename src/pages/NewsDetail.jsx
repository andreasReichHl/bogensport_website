import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";

const newsDayFormatter = new Intl.DateTimeFormat("de-DE", { day: "2-digit" });
const newsMonthFormatter = new Intl.DateTimeFormat("de-DE", { month: "short" });

function parseDate(value) {
  if (!value) {
    return null;
  }

  const date = new Date(value.replace(" ", "T"));

  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(value) {
  const date = parseDate(value);

  if (!date) {
    return "";
  }

  return new Intl.DateTimeFormat("de-DE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
}

function textPreview(html) {
  return html
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

export default function NewsDetail() {
  const { id } = useParams();
  const [post, setPost] = useState(null);
  const [posts, setPosts] = useState([]);
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    let isMounted = true;

    fetch("/news-feed.php")
      .then((response) => (response.ok ? response.json() : { posts: [] }))
      .then((data) => {
        if (!isMounted) {
          return;
        }

        const posts = Array.isArray(data.posts) ? data.posts : [];
        setPosts(posts);
        setPost(posts.find((item) => String(item.id) === String(id)) || null);
        setIsLoaded(true);
      })
      .catch(() => {
        if (isMounted) {
          setPosts([]);
          setPost(null);
          setIsLoaded(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [id]);

  if (!isLoaded) {
    return (
      <section className="mx-auto max-w-4xl px-5 py-16 sm:px-8 lg:px-12">
        <p className="text-slate-700">News wird geladen.</p>
      </section>
    );
  }

  if (!post) {
    return (
      <section className="mx-auto max-w-4xl px-5 py-16 sm:px-8 lg:px-12">
        <h1 className="font-heading text-3xl font-bold text-[#002f5f]">News nicht gefunden</h1>
        <Link className="mt-6 inline-flex text-blue-700" to="/">
          Zur Startseite
        </Link>
      </section>
    );
  }

  return (
    <article className="bg-white">
      <div className="mx-auto grid max-w-7xl gap-12 px-5 py-12 sm:px-8 lg:grid-cols-[320px_minmax(0,1fr)] lg:px-12">
        <aside className="lg:sticky lg:top-8 lg:self-start">
          <h2 className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700">News</h2>
          <nav className="mt-5 space-y-3" aria-label="Verfügbare News-Beiträge">
            {posts.map((item) => {
              const isCurrent = String(item.id) === String(id);
              const itemDate = parseDate(item.created_at);

              return (
                <Link
                  key={item.id}
                  to={`/news/${item.id}`}
                  className={`flex gap-3 rounded-md border px-3 py-3 transition hover:text-blue-700 ${
                    isCurrent
                      ? "border-blue-200 bg-blue-50 text-[#002f5f]"
                      : "border-transparent text-slate-700 hover:border-slate-200 hover:bg-slate-50"
                  }`}
                  aria-current={isCurrent ? "page" : undefined}
                >
                  {itemDate && (
                    <span className={`font-heading min-w-11 text-center ${isCurrent ? "text-[#002f5f]" : "text-slate-500"}`}>
                      <span className="block text-3xl font-bold leading-none">{newsDayFormatter.format(itemDate)}</span>
                      <span className="mt-1 block text-xs font-bold uppercase leading-none">{newsMonthFormatter.format(itemDate)}</span>
                    </span>
                  )}
                  <span className={`min-w-0 border-l pl-3 ${isCurrent ? "border-blue-200" : "border-slate-300"}`}>
                    <span className="block truncate font-heading text-base font-bold text-slate-900">{item.title}</span>
                    <span className="mt-1 block truncate text-sm leading-snug text-slate-600">
                      {textPreview(item.body_html)}
                    </span>
                  </span>
                </Link>
              );
            })}
          </nav>
        </aside>

        <div className="max-w-4xl lg:pl-2">
          {post.image_path && (
            <img className="mb-12 w-full rounded-lg" src={post.image_path} alt="" />
          )}
          <Link className="font-heading text-sm font-bold text-blue-700" to="/">
            Zurück zur Startseite
          </Link>
          <h1 className="mt-6 font-heading text-4xl font-bold leading-tight text-[#002f5f] sm:text-5xl">
            {post.title}
          </h1>
          {post.created_at && (
            <p className="mt-4 text-sm font-semibold text-slate-500">
              Vom {formatDate(post.created_at)}
            </p>
          )}
          <div
            className="mt-8 space-y-4 text-lg leading-relaxed text-slate-700 [&_.news-size-small]:text-[.88em] [&_a]:text-blue-700 [&_blockquote]:border-l-4 [&_blockquote]:border-blue-300 [&_blockquote]:bg-blue-50 [&_blockquote]:px-4 [&_blockquote]:py-3 [&_h4]:font-heading [&_h4]:text-lg [&_h4]:font-bold [&_h4]:text-inherit [&_h5]:font-heading [&_h5]:text-base [&_h5]:font-bold [&_h5]:text-inherit [&_h6]:font-heading [&_h6]:text-sm [&_h6]:font-bold [&_h6]:text-inherit [&_li]:ml-6 [&_ol]:list-decimal [&_ul]:list-disc"
            dangerouslySetInnerHTML={{ __html: post.body_html }}
          />
        </div>
      </div>
    </article>
  );
}
