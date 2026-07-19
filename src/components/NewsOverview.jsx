import { useEffect, useState } from "react";
import { Link } from "react-router-dom";

function textPreview(html) {
  const text = html
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();

  return text.length > 170 ? `${text.slice(0, 167)}...` : text;
}

export default function NewsOverview() {
  const [posts, setPosts] = useState([]);

  useEffect(() => {
    let isMounted = true;

    fetch("/news-feed.php")
      .then((response) => (response.ok ? response.json() : { posts: [] }))
      .then((data) => {
        if (isMounted) {
          setPosts(Array.isArray(data.posts) ? data.posts.slice(0, 3) : []);
        }
      })
      .catch(() => {
        if (isMounted) {
          setPosts([]);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  if (posts.length === 0) {
    return null;
  }

  return (
    <section className="bg-slate-50 pb-12 sm:pb-16">
      <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <p className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700">News</p>
        <h2 className="mt-4 font-heading text-3xl font-bold leading-tight text-slate-900 sm:text-4xl">
          Aktuelles aus dem Verein
        </h2>
        <div className="mt-5 h-0.5 w-20 bg-blue-600" />

        <div className="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {posts.map((post) => (
            <Link
              key={post.id}
              to={`/news/${post.id}`}
              className="block overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-900 shadow-lg shadow-slate-200/70 transition hover:border-blue-300 hover:shadow-xl hover:shadow-slate-200/80"
            >
              {post.image_path && (
                <img
                  className="h-48 w-full object-cover"
                  src={post.image_path}
                  alt=""
                  loading="lazy"
                />
              )}
              <div className="p-6">
                <h3 className="font-heading text-xl font-bold text-[#002f5f]">{post.title}</h3>
                <p className="mt-4 text-base leading-relaxed text-slate-700">{textPreview(post.body_html)}</p>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
