import { Link } from "react-router-dom";
import FadeInImage from "./FadeInImage";
import { formatDate, parseDate, textPreview, useNewsFeed } from "../utils/news";

const NEWS_PREVIEW_LIMIT = 3;
const PREVIEW_TEXT_MAX_LENGTH = 170;

function latestNewsFirst(left, right) {
  const leftDate = parseDate(left.created_at)?.getTime() ?? 0;
  const rightDate = parseDate(right.created_at)?.getTime() ?? 0;

  if (leftDate !== rightDate) {
    return rightDate - leftDate;
  }

  return Number(right.id || 0) - Number(left.id || 0);
}

export default function NewsOverview() {
  const { posts: allPosts } = useNewsFeed();
  const posts = [...allPosts].sort(latestNewsFirst).slice(0, NEWS_PREVIEW_LIMIT);

  if (posts.length === 0) {
    return null;
  }

  return (
    <section id="news" className="scroll-mt-24 bg-slate-50 pb-12 sm:pb-16">
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
              className="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-900 shadow-lg shadow-slate-200/70 transition hover:border-blue-300 hover:shadow-xl hover:shadow-slate-200/80"
            >
              {post.image_path && (
                <div className="p-4 pb-0">
                  <FadeInImage
                    wrapperClassName="aspect-[16/9] rounded-md bg-slate-100"
                    className="h-full w-full object-cover"
                    src={post.image_path}
                    alt=""
                    loading="lazy"
                  />
                </div>
              )}
              <div className="flex flex-1 flex-col p-6">
                <h3 className="font-heading text-xl font-bold text-[#002f5f]">{post.title}</h3>
                <p className="mt-4 text-base leading-relaxed text-slate-700">{textPreview(post.body_html, PREVIEW_TEXT_MAX_LENGTH)}</p>
                {post.created_at && (
                  <p className="mt-auto pt-6 text-xs font-semibold text-slate-500">
                    {formatDate(post.created_at)}
                  </p>
                )}
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
