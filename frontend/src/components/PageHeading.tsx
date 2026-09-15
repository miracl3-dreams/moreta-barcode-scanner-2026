type PageHeadingProps = {
  title: string
  description?: string
  className?: string
}

export function PageHeading({ title, description, className = '' }: PageHeadingProps) {
  return (
    <div className={className}>
      <h1 className="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{title}</h1>
      {description ? <p className="mt-1.5 text-sm text-slate-500 sm:mt-2 sm:text-base">{description}</p> : null}
    </div>
  )
}
