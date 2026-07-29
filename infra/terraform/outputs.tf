output "alb_dns_name" {
  value = aws_lb.app.dns_name
}

output "ecr_repository_url" {
  value = aws_ecr_repository.app.repository_url
}

output "github_deploy_role_arn" {
  value = aws_iam_role.github_deploy.arn
}

output "media_bucket" {
  value = aws_s3_bucket.media.id
}

output "media_cdn_domain" {
  value = aws_cloudfront_distribution.media.domain_name
}
