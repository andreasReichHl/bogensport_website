import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";

export default function NewsDetail() {
  const { id } = useParams();
  const [post, setPost] = useState(null);
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
        setPost(posts.find((item) => String(item.id) === String(id)) || null);
        setIsLoaded(true);
      })
      .catch(() => {
        if (isMounted) {
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
      {post.image_path && (
        <img className="h-[320px] w-full object-cover sm:h-[420px]" src={post.image_path} alt="" />
      )}
      <div className="mx-auto max-w-4xl px-5 py-12 sm:px-8 lg:px-12">
        <Link className="font-heading text-sm font-bold text-blue-700" to="/">
          Zurück zur Startseite
        </Link>
        <h1 className="mt-6 font-heading text-4xl font-bold leading-tight text-[#002f5f] sm:text-5xl">
          {post.title}
        </h1>
        <div
          className="mt-8 space-y-4 text-lg leading-relaxed text-slate-700 [&_a]:text-blue-700 [&_blockquote]:border-l-4 [&_blockquote]:border-blue-300 [&_blockquote]:bg-blue-50 [&_blockquote]:px-4 [&_blockquote]:py-3 [&_h2]:font-heading [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:font-heading [&_h3]:text-xl [&_h3]:font-bold [&_li]:ml-6 [&_ol]:list-decimal [&_ul]:list-disc"
          dangerouslySetInnerHTML={{ __html: post.body_html }}
        />
      </div>
    </article>
  );
}
